<?php

namespace webdna\componentlibrary\migrations;

use craft\db\Migration;
use craft\db\Table;
use webdna\componentlibrary\records\ShareRecord;

/**
 * Creates the share-link table, the plugin's only table (spec §4).
 */
class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable(ShareRecord::TABLE, [
            'id' => $this->primaryKey(),
            'label' => $this->string(100)->notNull(),
            // SHA-256 hex of the token, which every lookup uses.
            'tokenHash' => $this->char(64)->notNull(),
            // The token encrypted with Craft's security key, for Copy link only (BR-45).
            'tokenEncrypted' => $this->text(),
            'expiresAt' => $this->dateTime()->notNull(),
            'createdById' => $this->integer()->notNull(),
            'lastUsedAt' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, ShareRecord::TABLE, ['tokenHash'], true);
        $this->addForeignKey(null, ShareRecord::TABLE, ['createdById'], Table::USERS, ['id'], 'CASCADE');

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(ShareRecord::TABLE);

        return true;
    }
}
