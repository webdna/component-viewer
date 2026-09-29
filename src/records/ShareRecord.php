<?php

namespace webdna\componentlibrary\records;

use craft\db\ActiveRecord;
use craft\records\User;
use yii\db\ActiveQueryInterface;

/**
 * A share link. Status is derived from expiresAt and revokedAt, never stored (spec §4).
 *
 * @property int $id
 * @property string $label
 * @property string $tokenHash
 * @property string|null $tokenEncrypted
 * @property string $expiresAt
 * @property string|null $revokedAt
 * @property int $createdById
 * @property string|null $lastUsedAt
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 * @property-read User $createdBy
 */
class ShareRecord extends ActiveRecord
{
    public const TABLE = '{{%componentlibrary_shares}}';

    public static function tableName(): string
    {
        return self::TABLE;
    }

    public function getCreatedBy(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'createdById']);
    }
}
