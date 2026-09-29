<?php

namespace webdna\componentlibrary\services;

use Craft;
use craft\db\ActiveQuery;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use DateTime;
use webdna\componentlibrary\models\ShareForm;
use webdna\componentlibrary\records\ShareRecord;
use yii\base\Component;

/**
 * Share links (BR-27, BR-28, BR-36): create, find, copy, revoke and clean up.
 *
 * Lookups use the token's SHA-256 hash, and the token is otherwise stored only encrypted (BR-45),
 * so a database leak alone leaks no working link. Status is derived from `expiresAt` on every read
 * (§4). Revoking deletes the row, so a revoked link is simply unknown.
 * Nothing here sends anything (BR-30).
 */
class Shares extends Component
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';

    /** The share viewer's path on the primary site. 4.2 routes it. */
    public const URL_PATH = 'component-library/share/';

    /** 32 bytes, base64url without padding. */
    public const TOKEN_PATTERN = '/^[A-Za-z0-9_-]{43}$/D';

    /** Rows are removed this many days after they expire (§4). */
    public const GC_DAYS = 30;

    /** `lastUsedAt` moves at most this often per link (§4). */
    private const TOUCH_SECONDS = 60;

    /**
     * BR-27: saves a link from a valid form, and returns its token, the only time it exists.
     * Null when the form doesn't validate; its errors say why.
     */
    public function create(ShareForm $form, int $creatorId): ?string
    {
        if (!$form->validate()) {
            return null;
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $record = new ShareRecord();
        $record->label = $form->label;
        $record->tokenHash = self::hash($token);
        // BR-45: never in plaintext. Craft's security key decrypts it for Copy link only.
        $record->tokenEncrypted = base64_encode(Craft::$app->getSecurity()->encryptByKey($token));
        $record->expiresAt = Db::prepareDateForDb($form->expiresAt());
        $record->createdById = $creatorId;
        $record->save(false);

        return $token;
    }

    /**
     * BR-45: an active link's address again, from its encrypted token. Null for a link that's
     * expired, unknown (revoked links included) or made before tokens were stored, or whose stored token
     * doesn't decrypt to a token of this link.
     */
    public function copyUrl(int $id): ?string
    {
        $record = ShareRecord::findOne($id);
        if ($record === null || $record->tokenEncrypted === null || $this->status($record) !== self::STATUS_ACTIVE) {
            return null;
        }

        $token = Craft::$app->getSecurity()->decryptByKey((string)base64_decode($record->tokenEncrypted, true));
        if (!is_string($token) || !preg_match(self::TOKEN_PATTERN, $token) || !hash_equals($record->tokenHash, self::hash($token))) {
            return null;
        }

        return $this->url($token);
    }

    /** The address a token opens (BR-27). */
    public function url(string $token): string
    {
        $site = Craft::$app->getSites()->getPrimarySite();

        return rtrim((string)$site->getBaseUrl(), '/') . '/' . self::URL_PATH . $token;
    }

    /** The link a token belongs to, in any state. Looked up by hash, never by the token. */
    public function find(string $token): ?ShareRecord
    {
        if (!preg_match(self::TOKEN_PATTERN, $token)) {
            return null;
        }

        $hash = self::hash($token);
        $record = ShareRecord::findOne(['tokenHash' => $hash]);

        // The database matched it already. This keeps the final comparison constant-time anyway.
        return $record !== null && hash_equals($record->tokenHash, $hash) ? $record : null;
    }

    /** Links that work right now: not expired (BR-27). A revoked link has no row. */
    public function active(): ActiveQuery
    {
        return ShareRecord::find()
            ->where(['>', 'expiresAt', Db::prepareDateForDb(new DateTime())]);
    }

    public function status(ShareRecord $record): string
    {
        return DateTimeHelper::toDateTime($record->expiresAt) > new DateTime()
            ? self::STATUS_ACTIVE
            : self::STATUS_EXPIRED;
    }

    /**
     * Revokes a link by deleting it (v0.27), from the next request on, previews included (BR-21):
     * its address and its previews' `share:<id>` scope then name nothing. False when there's no
     * such link.
     */
    public function revoke(int $id): bool
    {
        return ShareRecord::deleteAll(['id' => $id]) > 0;
    }

    /** Records a visit, at most once a minute per link. The share viewer (4.2) calls it. */
    public function markUsed(ShareRecord $record): void
    {
        $last = $record->lastUsedAt !== null ? DateTimeHelper::toDateTime($record->lastUsedAt) : null;
        if ($last !== false && $last !== null && time() - $last->getTimestamp() < self::TOUCH_SECONDS) {
            return;
        }

        $record->lastUsedAt = Db::prepareDateForDb(new DateTime());
        $record->save(false);
    }

    /**
     * Every link, newest first, as the share list shows it.
     *
     * `copyable` says whether *Copy link* can return its address (BR-45). The token itself never
     * reaches the list.
     *
     * @return list<array{id:int,label:string,creator:string,expiresAt:DateTime,lastUsedAt:DateTime|null,status:string,copyable:bool}>
     */
    public function rows(): array
    {
        $rows = [];

        foreach (ShareRecord::find()->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])->all() as $record) {
            /** @var ShareRecord $record */
            $user = Craft::$app->getUsers()->getUserById($record->createdById);
            $rows[] = [
                'id' => (int)$record->id,
                'label' => $record->label,
                'creator' => $user?->getName() ?? '',
                'expiresAt' => DateTimeHelper::toDateTime($record->expiresAt) ?: new DateTime(),
                'lastUsedAt' => $record->lastUsedAt !== null ? (DateTimeHelper::toDateTime($record->lastUsedAt) ?: null) : null,
                'status' => $status = $this->status($record),
                'copyable' => $status === self::STATUS_ACTIVE && $record->tokenEncrypted !== null,
            ];
        }

        return $rows;
    }

    /** §4: removes links expired more than GC_DAYS ago. Runs on Craft's GC. */
    public function gc(): int
    {
        $cutoff = Db::prepareDateForDb(new DateTime('-' . self::GC_DAYS . ' days'));

        return ShareRecord::deleteAll(['<', 'expiresAt', $cutoff]);
    }

    /**
     * §4, TN-13: a deleted user's links go with them. Craft soft-deletes users, so the foreign key's
     * cascade alone would leave the links working until the user is purged.
     */
    public function deleteForUser(int $userId): int
    {
        return ShareRecord::deleteAll(['createdById' => $userId]);
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
