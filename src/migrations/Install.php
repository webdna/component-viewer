<?php

namespace webdna\componentviewer\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use webdna\componentviewer\records\ShareRecord;

/**
 * Creates the share-link table, the plugin's only table (spec §4).
 *
 * The plugin was called Component Library, handle `component-library`, until 2.0.0-beta.3. Craft
 * sees the renamed plugin as a new one, so on a site that ran the old handle this migration takes
 * over what it left: the share table (so links already sent keep working) and the two permissions.
 */
class Install extends Migration
{
    private const OLD_TABLE = '{{%componentlibrary_shares}}';

    /** Old permission name => new, lowercased as Craft stores them. */
    private const OLD_PERMISSIONS = [
        'accessplugin-component-library' => 'accessplugin-component-viewer',
        'managecomponentlibraryshares' => 'managecomponentviewershares',
    ];

    public function safeUp(): bool
    {
        if ($this->db->tableExists(self::OLD_TABLE)) {
            $this->renameTable(self::OLD_TABLE, ShareRecord::TABLE);
        } else {
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
        }

        $this->carryOverPermissions();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(ShareRecord::TABLE);

        return true;
    }

    /**
     * Grants made under the old names. A user's own grants are database rows, renamed here. A
     * group's are project config, rewritten here once; another environment receives that change
     * with the rest of the config, so nothing is written while external changes are applied.
     */
    private function carryOverPermissions(): void
    {
        foreach (self::OLD_PERMISSIONS as $old => $new) {
            $taken = (new Query())->from(Table::USERPERMISSIONS)->where(['name' => $new])->exists($this->db);
            if (!$taken) {
                $this->update(Table::USERPERMISSIONS, ['name' => $new], ['name' => $old], updateTimestamp: false);
            }
        }

        $projectConfig = Craft::$app->getProjectConfig();
        if ($projectConfig->readOnly || $projectConfig->getIsApplyingExternalChanges()) {
            return;
        }

        foreach ($projectConfig->get('users.groups') ?? [] as $uid => $group) {
            $permissions = $group['permissions'] ?? [];
            $renamed = array_values(array_unique(array_map(
                fn(string $name) => self::OLD_PERMISSIONS[strtolower($name)] ?? $name,
                $permissions,
            )));

            if ($renamed !== $permissions) {
                $projectConfig->set(
                    "users.groups.$uid.permissions",
                    $renamed,
                    'Carry Component Library permissions over to Component Viewer',
                );
            }
        }
    }
}
