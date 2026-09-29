<?php

namespace webdna\componentlibrary\migrations;

use craft\db\Migration;
use webdna\componentlibrary\records\ShareRecord;

/**
 * Adds the encrypted token that *Copy link* reads (BR-45). Links made before it have none, so
 * they keep working but can't be copied again.
 */
class m260929_000000_share_token_encrypted extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(ShareRecord::TABLE, 'tokenEncrypted')) {
            $this->addColumn(ShareRecord::TABLE, 'tokenEncrypted', $this->text()->after('tokenHash'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists(ShareRecord::TABLE, 'tokenEncrypted')) {
            $this->dropColumn(ShareRecord::TABLE, 'tokenEncrypted');
        }

        return true;
    }
}
