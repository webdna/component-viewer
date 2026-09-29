<?php

declare(strict_types=1);

/*
 * TS-4, TN-12 and TN-13: creating, listing, cancelling and cleaning up share links (BR-27, BR-28,
 * BR-30, BR-36). The gates and CSRF (TN-11) are AccessTest's.
 *
 * Share rows are made per test and rolled back with it. Forms post to the list page with an
 * `action` param, as the CP form does, so a failed create re-renders that page with its errors.
 */

use craft\elements\User;
use craft\helpers\Db;
use craft\mail\Mailer;
use craft\queue\Queue as CraftQueue;
use craft\services\Gc;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\controllers\SharesController;
use webdna\componentlibrary\models\ShareForm;
use webdna\componentlibrary\records\ShareRecord;
use webdna\componentlibrary\services\Shares;
use yii\base\Event;
use yii\mail\MailEvent;

const SHARES_PAGE = 'admin/component-library/shares';

beforeEach(function() {
    $this->withExceptionHandling();
    $this->shares = ComponentLibrary::getInstance()->getShares();
    // The list and its empty state are about this test's rows only.
    ShareRecord::deleteAll();
    // craft-pest keeps one session for the run, so an unread address would leak into the next test.
    Craft::$app->getSession()->removeFlash(SharesController::FLASH_URL);
});

function sharesPost(array $body, string $action = 'create'): array
{
    return ['action' => "component-library/shares/$action"] + $body;
}

function shareRow(string $expires = '+14 days', ?string $revoked = null, string $label = 'Acme', ?int $creator = null): ShareRecord
{
    $share = new ShareRecord();
    $share->label = $label;
    $share->tokenHash = hash('sha256', random_bytes(32));
    $share->expiresAt = Db::prepareDateForDb(new DateTime($expires));
    $share->revokedAt = $revoked === null ? null : Db::prepareDateForDb(new DateTime($revoked));
    $share->createdById = $creator ?? (int)User::find()->username('admin')->one()->id;
    $share->save(false);

    return $share;
}

/** The only share row, or null. */
function onlyShare(): ?ShareRecord
{
    /** @var ShareRecord|null $share */
    $share = ShareRecord::find()->one();

    return $share;
}

function queuedJobs(): int
{
    /** @var CraftQueue $queue */
    $queue = Craft::$app->getQueue();

    return $queue->getTotalJobs();
}

/** The one-time address a page opens in the copy prompt, or null. */
function shownShareUrl(string $html): ?string
{
    return preg_match('#data-cl-share-url="([^"]+)"#', $html, $match) ? html_entity_decode($match[1]) : null;
}

describe('TS-4 create', function() {
    // TS-4 step 1
    it('pre-fills the expiry two weeks out, from tomorrow to three months, with no links yet', function() {
        $html = $this->actingAs('admin')->get(SHARES_PAGE)->assertOk()->content;

        expect($html)->toMatch('#<input[^>]+name="expiry"[^>]+value="' . ShareForm::day(14) . '"#')
            ->and($html)->toMatch('#<input[^>]+type="date"[^>]+name="expiry"#')
            ->and($html)->toContain('min="' . ShareForm::day(1) . '"')
            ->and($html)->toContain('max="' . ShareForm::day(90) . '"')
            ->and($html)->toContain('No share links yet')
            ->and($html)->toContain('href="#cl-share-label"')
            ->and($html)->not->toContain('data-cl-share-url="');
    });

    // TS-4 step 3, BR-27, BR-36
    it('saves only a hash of the token and shows the address once', function() {
        $this->actingAs('admin');
        $response = $this->post(SHARES_PAGE, sharesPost(['label' => 'Acme', 'expiry' => ShareForm::day(14)]));
        $response->assertStatus(302);
        expect(parse_url((string)$response->headers->get('Location'), PHP_URL_PATH))->toBe('/admin/component-library/shares');

        expect(ShareRecord::find()->count())->toBe(1);
        $row = onlyShare();

        $url = shownShareUrl($this->get(SHARES_PAGE)->assertOk()->content);
        $primary = rtrim((string)Craft::$app->getSites()->getPrimarySite()->getBaseUrl(), '/');
        expect($url)->toMatch('#^' . preg_quote($primary, '#') . '/component-library/share/[A-Za-z0-9_-]{43}$#');

        $token = substr($url, -43);
        expect($row->tokenHash)->toMatch('/^[0-9a-f]{64}$/')
            ->and($row->tokenHash)->toBe(hash('sha256', $token))
            ->and($row->label)->toBe('Acme')
            ->and((int)$row->createdById)->toBe((int)User::find()->username('admin')->one()->id)
            ->and($row->expiresAt)->toBe(ShareForm::day(14) . ' 23:59:59')
            ->and($row->revokedAt)->toBeNull()
            ->and($row->lastUsedAt)->toBeNull()
            ->and(implode('|', $row->getAttributes()))->not->toContain($token)
            // BR-45: kept only encrypted with Craft's security key.
            ->and(Craft::$app->getSecurity()->decryptByKey(base64_decode((string)$row->tokenEncrypted)))->toBe($token);

        // The service finds it by the token, and by nothing else.
        expect($this->shares->find($token)?->id)->toBe($row->id)
            ->and($this->shares->find($row->tokenHash))->toBeNull()
            ->and($this->shares->find(strrev($token)))->toBeNull()
            ->and($this->shares->find($token . 'x'))->toBeNull();
    });

    // TS-4 step 4
    it('never shows the address again, and lists the link as active', function() {
        $this->actingAs('admin')->post(SHARES_PAGE, sharesPost(['label' => 'Acme', 'expiry' => ShareForm::day(14)]));
        expect(shownShareUrl($this->get(SHARES_PAGE)->content))->not->toBeNull();

        $html = $this->get(SHARES_PAGE)->assertOk()->content;
        expect(shownShareUrl($html))->toBeNull()
            ->and($html)->toContain('data-cl-share-state="active"')
            ->and($html)->toContain('<th scope="row">Acme</th>')
            ->and($html)->not->toContain('No share links yet');
    });

    it('trims the label', function() {
        $this->actingAs('admin')->post(SHARES_PAGE, sharesPost(['label' => "  Acme \n", 'expiry' => ShareForm::day(14)]));

        expect(onlyShare()?->label)->toBe('Acme');
    });

    it('accepts the bounds: 100 characters, tomorrow and three months', function(string $label, int $days) {
        $this->actingAs('admin')->post(SHARES_PAGE, sharesPost(['label' => $label, 'expiry' => ShareForm::day($days)]))
            ->assertStatus(302);

        expect(ShareRecord::find()->count())->toBe(1);
    })->with([
        '100 characters' => [str_repeat('é', 100), 14],
        'tomorrow' => ['Acme', 1],
        'three months' => ['Acme', 90],
    ]);

    // BR-30, TS-4 step 3
    it('sends nothing and queues nothing', function() {
        $sent = 0;
        $count = function(MailEvent $event) use (&$sent) {
            $sent++;
        };
        Event::on(Mailer::class, Mailer::EVENT_BEFORE_SEND, $count);
        $jobs = queuedJobs();

        try {
            $this->actingAs('admin')->post(SHARES_PAGE, sharesPost(['label' => 'Acme', 'expiry' => ShareForm::day(14)]));
            $id = (int)onlyShare()?->id;
            $this->post(SHARES_PAGE, sharesPost(['id' => $id], 'revoke'));
        } finally {
            Event::off(Mailer::class, Mailer::EVENT_BEFORE_SEND, $count);
        }

        expect($sent)->toBe(0)
            ->and(queuedJobs())->toBe($jobs);
    });

    // BR-36
    it('holds nothing about the recipient', function() {
        $columns = Craft::$app->getDb()->getTableSchema(ShareRecord::TABLE, true)->getColumnNames();
        sort($columns);

        expect($columns)->toBe(['createdById', 'dateCreated', 'dateUpdated', 'expiresAt', 'id', 'label', 'lastUsedAt', 'revokedAt', 'tokenEncrypted', 'tokenHash', 'uid']);
    });
});

// TS-4 step 2, TN-12
describe('TN-12 validation', function() {
    it('refuses a bad label or expiry with an error and no row', function(string $label, string $expiry, string $error) {
        $response = $this->actingAs('admin')->post(SHARES_PAGE, sharesPost(['label' => $label, 'expiry' => $expiry]));

        $response->assertOk();
        expect($response->content)->toContain(htmlspecialchars($error, ENT_QUOTES))
            ->and(shownShareUrl($response->content))->toBeNull()
            ->and(ShareRecord::find()->count())->toBe(0);
    })->with([
        'empty label' => ['', ShareForm::day(14), 'Label cannot be blank.'],
        'blank label' => ['   ', ShareForm::day(14), 'Label cannot be blank.'],
        '101 characters' => [str_repeat('a', 101), ShareForm::day(14), 'Label should contain at most 100 characters.'],
        'three months and a day' => ['Acme', ShareForm::day(91), 'Choose a date from'],
        'today' => ['Acme', ShareForm::day(0), 'Choose a date from'],
        'yesterday' => ['Acme', ShareForm::day(-1), 'Choose a date from'],
        'no date' => ['Acme', '', 'Expires cannot be blank.'],
        'not a day' => ['Acme', '2026-02-30', 'Choose a date from'],
        'not a date' => ['Acme', 'next week', 'Choose a date from'],
    ]);

    it('keeps what was typed after a refusal', function() {
        $html = $this->actingAs('admin')
            ->post(SHARES_PAGE, sharesPost(['label' => 'Acme <b>', 'expiry' => ShareForm::day(91)]))
            ->content;

        expect($html)->toMatch('#<input[^>]+name="label"[^>]+value="Acme &lt;b&gt;"#')
            ->and($html)->toMatch('#<input[^>]+name="expiry"[^>]+value="' . ShareForm::day(91) . '"#');
    });
});

describe('cancel', function() {
    it('cancels a link at once, previews included, and keeps its first cancel time', function() {
        $share = shareRow();
        $renderer = ComponentLibrary::getInstance()->getRenderer();
        expect($renderer->scopeIsValid("share:$share->id"))->toBeTrue();

        $this->actingAs('admin')->post(SHARES_PAGE, sharesPost(['id' => $share->id], 'revoke'))->assertStatus(302);
        $share->refresh();
        expect($share->revokedAt)->not->toBeNull()
            ->and($renderer->scopeIsValid("share:$share->id"))->toBeFalse()
            ->and($this->shares->status($share))->toBe(Shares::STATUS_CANCELLED);

        $share->revokedAt = '2026-01-01 00:00:00';
        $share->save(false);
        $this->post(SHARES_PAGE, sharesPost(['id' => $share->id], 'revoke'))->assertStatus(302);
        expect($share->refresh() ? $share->revokedAt : null)->toBe('2026-01-01 00:00:00');
    });

    it('offers cancel on active links only, with a confirmation', function() {
        $active = shareRow(label: 'Live');
        shareRow('-1 day', label: 'Old');
        shareRow(revoked: '-1 hour', label: 'Gone');

        $html = $this->actingAs('admin')->get(SHARES_PAGE)->assertOk()->content;

        expect(substr_count($html, 'data-action="component-library/shares/revoke"'))->toBe(1)
            ->and($html)->toContain('data-value="' . $active->id . '"')
            ->and($html)->toContain('data-confirm="Cancel “Live”?')
            ->and($html)->toContain('data-cl-share-state="active"')
            ->and($html)->toContain('data-cl-share-state="expired"')
            ->and($html)->toContain('data-cl-share-state="cancelled"');
    });

    it('404s an unknown link', function() {
        $this->actingAs('admin')->post(SHARES_PAGE, sharesPost(['id' => 999999], 'revoke'))->assertStatus(404);
    });
});

// BR-27, §4 States
it('derives the status from the clock and the cancel time', function(string $expires, ?string $revoked, string $status) {
    $share = shareRow($expires, $revoked);

    expect(ComponentLibrary::getInstance()->getShares()->status($share))->toBe($status)
        ->and(ComponentLibrary::getInstance()->getShares()->active()->andWhere(['id' => $share->id])->exists())
        ->toBe($status === Shares::STATUS_ACTIVE);
})->with([
    'active' => ['+1 hour', null, Shares::STATUS_ACTIVE],
    'expired' => ['-1 second', null, Shares::STATUS_EXPIRED],
    'cancelled' => ['+1 day', '-1 hour', Shares::STATUS_CANCELLED],
    'cancelled, then expired' => ['-1 day', '-2 days', Shares::STATUS_CANCELLED],
]);

// §4 lastUsedAt
it('records use at most once a minute', function() {
    $share = shareRow();
    $shares = ComponentLibrary::getInstance()->getShares();

    $shares->markUsed($share);
    $first = $share->refresh() ? $share->lastUsedAt : null;
    expect($first)->not->toBeNull();

    $share->lastUsedAt = Db::prepareDateForDb(new DateTime('-30 seconds'));
    $share->save(false);
    $held = $share->lastUsedAt;
    $shares->markUsed($share);
    expect($share->refresh() ? $share->lastUsedAt : null)->toBe($held);

    $share->lastUsedAt = Db::prepareDateForDb(new DateTime('-61 seconds'));
    $share->save(false);
    $shares->markUsed($share);
    expect($share->refresh() ? $share->lastUsedAt : null)->not->toBe($held);
});

// §4 On deletion
it('garbage-collects links 30 days after they expire or are cancelled', function() {
    $kept = [
        shareRow()->id,
        shareRow('-29 days')->id,
        shareRow('+1 day', '-29 days')->id,
    ];
    shareRow('-31 days');
    shareRow('+1 day', '-31 days');

    Craft::$app->getGc()->trigger(Gc::EVENT_RUN);

    expect(ShareRecord::find()->select('id')->column())->toEqualCanonicalizing($kept);
});

// TN-13
it('deletes a user’s links with the user', function() {
    $user = new User(['username' => 'cl-tn13', 'email' => 'cl-tn13@example.test']);
    expect(Craft::$app->getElements()->saveElement($user, false))->toBeTrue();

    $this->actingAs('admin')->post(SHARES_PAGE, sharesPost(['label' => 'Acme', 'expiry' => ShareForm::day(14)]));
    $token = substr((string)shownShareUrl($this->get(SHARES_PAGE)->content), -43);
    $theirs = ShareRecord::findOne(['tokenHash' => hash('sha256', $token)]);
    $theirs->createdById = (int)$user->id;
    $theirs->save(false);
    $other = shareRow();

    Craft::$app->getElements()->deleteElement($user);

    expect($this->shares->find($token))->toBeNull()
        ->and(ShareRecord::find()->select('id')->column())->toEqual([$other->id]);
});
