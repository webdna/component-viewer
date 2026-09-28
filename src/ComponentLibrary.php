<?php

namespace webdna\componentlibrary;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use yii\base\Event;
use yii\base\InvalidConfigException;

/**
 * Component Library plugin
 *
 * @method static ComponentLibrary getInstance()
 * @author webdna
 * @copyright webdna
 * @license proprietary
 */
class ComponentLibrary extends Plugin
{
    /**
     * View the library in the control panel (BR-1).
     *
     * This is Craft's own section permission, not one we register. Craft refuses any CP request
     * whose first URI segment is a plugin handle unless the user holds `accessPlugin-<handle>`
     * (`web/Application.php`), before a controller runs, and hides the nav item without it
     * (`web/twig/variables/Cp.php`). A permission of our own would sit behind that one, so a user
     * given only ours would see a section that refuses them. `accessCp` does not grant it.
     */
    public const PERMISSION_VIEW = 'accessPlugin-component-library';

    /**
     * Create and cancel share links (BR-1), nested under PERMISSION_VIEW.
     */
    public const PERMISSION_MANAGE_SHARES = 'manageComponentLibraryShares';

    public string $schemaVersion = '2.0.0';
    public bool $hasCpSection = true;

    public function init(): void
    {
        // BR-4: v1 was a module, and v1 installs list it in config/app.php. Loaded that way the
        // class would boot without its table or permissions, so refuse outright. Only Craft's
        // plugin loader sets packageName (from Composer), so its absence means a module load.
        if ($this->packageName === null) {
            throw new InvalidConfigException(
                'Component Library 2 is a Craft plugin, not a module. Remove ' .
                "'component-library' => " . self::class . "::class from 'modules', and " .
                "'component-library' from 'bootstrap', in config/app.php, then run " .
                '`php craft plugin/install component-library`.'
            );
        }

        parent::init();

        $this->registerPermissions();
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();

        if (Craft::$app->getUser()->checkPermission(self::PERMISSION_MANAGE_SHARES)) {
            $item['subnav'] = [
                'library' => [
                    'label' => Craft::t('component-library', 'Library'),
                    'url' => 'component-library',
                ],
                'shares' => [
                    'label' => Craft::t('component-library', 'Share links'),
                    'url' => 'component-library/shares',
                ],
            ];
        }

        return $item;
    }

    private function registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event): void {
                $nested = [
                    self::PERMISSION_MANAGE_SHARES => [
                        'label' => Craft::t('component-library', 'Create and cancel share links'),
                    ],
                ];

                foreach ($event->permissions as &$group) {
                    if ($this->nestUnder($group['permissions'], self::PERMISSION_VIEW, $nested)) {
                        return;
                    }
                }
            },
        );
    }

    /**
     * Adds $nested beneath the permission named $parent, wherever it sits in the tree.
     *
     * @param array<string,mixed> $permissions
     * @param array<string,mixed> $nested
     */
    private function nestUnder(array &$permissions, string $parent, array $nested): bool
    {
        foreach ($permissions as $name => &$permission) {
            if ($name === $parent) {
                $permission['nested'] = array_merge($permission['nested'] ?? [], $nested);
                return true;
            }

            if (isset($permission['nested']) && $this->nestUnder($permission['nested'], $parent, $nested)) {
                return true;
            }
        }

        return false;
    }
}
