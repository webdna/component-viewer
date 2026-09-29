<?php

namespace webdna\componentlibrary\migrations;

use craft\db\Migration;
use webdna\componentlibrary\records\ShareRecord;

/**
 * Revoking now deletes a link (v0.27), so cancelled links go and so does `revokedAt`. Their
 * addresses were already refused, and stay refused, now as unknown (404).
 */
class m260929_000001_share_revoke_deletes extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->columnExists(ShareRecord::TABLE, 'revokedAt')) {
            $this->delete(ShareRecord::TABLE, ['not', ['revokedAt' => null]]);
            $this->dropColumn(ShareRecord::TABLE, 'revokedAt');
        }

        return true;
    }

    public function safeDown(): bool
    {
        // The deleted links can't come back, but the column can.
        if (!$this->db->columnExists(ShareRecord::TABLE, 'revokedAt')) {
            $this->addColumn(ShareRecord::TABLE, 'revokedAt', $this->dateTime()->after('expiresAt'));
        }

        return true;
    }
}
