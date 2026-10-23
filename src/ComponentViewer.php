<?php

namespace webdna\componentviewer;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\User;
use craft\events\CreateTwigEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Gc;
use craft\services\UserPermissions;
use craft\utilities\ClearCaches;
use craft\web\UrlManager;
use craft\web\View;
use webdna\componentviewer\models\Settings;
use webdna\componentviewer\services\Index;
use webdna\componentviewer\services\Renderer;
use webdna\componentviewer\services\Resolver;
use webdna\componentviewer\services\Shares;
use webdna\componentviewer\services\Viewer;
use webdna\componentviewer\twig\Extension;
use webdna\componentviewer\twig\Loader;
use yii\base\Event;
use yii\base\InvalidConfigException;

/**
 * Component Viewer plugin
 *
 * @method static ComponentViewer getInstance()
 * @property-read Index $index
 * @property-read Resolver $resolver
 * @property-read Renderer $renderer
 * @property-read Viewer $viewer
 * @property-read Shares $shares
 * @author webdna
 * @copyright webdna
 * @license proprietary
 */
class ComponentViewer extends Plugin
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
    public const PERMISSION_VIEW = 'accessPlugin-component-viewer';

    /**
     * Create and revoke share links (BR-1), nested under PERMISSION_VIEW.
     */
    public const PERMISSION_MANAGE_SHARES = 'manageComponentViewerShares';

    public string $schemaVersion = '2.0.2';
    public bool $hasCpSection = true;

    /**
     * The services. The index's roots (BR-11) and icons folder (BR-44) and the renderer's layout
     * (BR-25) come from the settings, which init() applies once Craft has set them.
     */
    public static function config(): array
    {
        return [
            'components' => [
                'index' => Index::class,
                'resolver' => Resolver::class,
                'renderer' => Renderer::class,
                'viewer' => Viewer::class,
                'shares' => Shares::class,
            ],
        ];
    }

    public function init(): void
    {
        // BR-4: v1 was a module, and v1 installs list it in config/app.php. Loaded that way the
        // class would boot without its table or permissions, so refuse outright. Only Craft's
        // plugin loader sets packageName (from Composer), so its absence means a module load.
        // v1's own class name, webdna\componentlibrary\ComponentLibrary, is caught by the shim in
        // shim/ComponentLibrary.php, which gives the same instruction.
        if ($this->packageName === null) {
            throw new InvalidConfigException(
                'Component Viewer is a Craft plugin, not a module. Remove it from \'modules\' and ' .
                '\'bootstrap\' in config/app.php, then run `php craft plugin/install component-viewer`.'
            );
        }

        parent::init();

        $this->configureServices();
        $this->registerPermissions();
        $this->registerCpRoutes();
        $this->registerSiteRoutes();
        $this->registerSiteTemplateRoot();
        $this->registerCacheOption();
        $this->registerShareCleanup();

        // Site and CP template modes both, since a component compiles wherever it's included.
        Craft::$app->getView()->registerTwigExtension(new Extension());
        $this->registerLoader();
    }

    /**
     * config/component-viewer.php. Craft reads it as the plugin's settings, so a plugin without a
     * settings model logs "Attempting to set settings on a plugin that doesn't have settings" on
     * every request.
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    public function getSettings(): Settings
    {
        /** @var Settings $settings */
        $settings = parent::getSettings();

        return $settings;
    }

    /**
     * Hands the settings to the services that read them; a setting left null keeps the service's
     * default. A site that still has the file under the plugin's old name, config/component-library.php,
     * keeps working, with a deprecation warning to rename it.
     */
    private function configureServices(): void
    {
        $settings = $this->getSettings();
        $configService = Craft::$app->getConfig();

        if (
            $configService->getConfigFromFile('component-viewer') === [] &&
            is_file($configService->getConfigFilePath('component-library'))
        ) {
            $settings->setAttributes($configService->getConfigFromFile('component-library'), false);
            Craft::$app->getDeprecator()->log(
                'component-viewer:config-file',
                'Rename config/component-library.php to config/component-viewer.php. The plugin was renamed from Component Library to Component Viewer.',
            );
        }

        $set = fn(array $values) => array_filter($values, fn($value) => $value !== null);

        $this->set('index', $set([
            'class' => Index::class,
            'templateDirectories' => $settings->templateDirectories,
            'sites' => $settings->sites,
            'legacy' => $settings->legacy,
            'icons' => $settings->icons,
        ]));
        $this->set('renderer', $set([
            'class' => Renderer::class,
            'layout' => $settings->layout,
        ]));
    }

    public function getIndex(): Index
    {
        return $this->get('index');
    }

    /** BR-19's public API: a file's path across the roots, for other template engines. */
    public function getResolver(): Resolver
    {
        return $this->get('resolver');
    }

    /** The preview render (BR-20 to BR-26), and the tokens that reach it. */
    public function getRenderer(): Renderer
    {
        return $this->get('renderer');
    }

    /** What the CP and share viewers show for a request (§6 Screens). */
    public function getViewer(): Viewer
    {
        return $this->get('viewer');
    }

    /** Share links (BR-27, BR-28). */
    public function getShares(): Shares
    {
        return $this->get('shares');
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();

        if (Craft::$app->getUser()->checkPermission(self::PERMISSION_MANAGE_SHARES)) {
            $item['subnav'] = [
                'library' => [
                    'label' => Craft::t('component-viewer', 'Library'),
                    'url' => 'component-viewer',
                ],
                'shares' => [
                    'label' => Craft::t('component-viewer', 'Share links'),
                    'url' => 'component-viewer/shares',
                ],
            ];
        }

        return $item;
    }

    private function registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event): void {
                $event->rules['component-viewer'] = 'component-viewer/viewer/index';
                $event->rules['component-viewer/shares'] = 'component-viewer/shares/index';
                // Owned handles only (BR-17), so `shares` and any other word can never be one.
                $event->rules['component-viewer/<handle:@[A-Za-z0-9_:-]+>'] = 'component-viewer/viewer/index';
            },
        );
    }

    /**
     * The share viewer (BR-27). Any token and handle reach the controller, so every refusal is its
     * own page with its headers, not the site's 404.
     */
    private function registerSiteRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event): void {
                // Links sent before the rename from Component Library still open (LEGACY_URL_PATH).
                foreach ([Shares::URL_PATH, Shares::LEGACY_URL_PATH] as $path) {
                    $event->rules[$path . '<shareToken:[^/]+>'] = 'component-viewer/share-viewer/index';
                    $event->rules[$path . '<shareToken:[^/]+>/<handle:[^/]+>'] = 'component-viewer/share-viewer/index';
                }
            },
        );
    }

    /**
     * The preview's own site templates (BR-25, BR-26). A folder of their own, so none of the CP
     * templates can be reached from the front end. Craft only checks a root's inner path for
     * private segments, so they sit in `_render` inside it, which its router never serves.
     */
    private function registerSiteTemplateRoot(): void
    {
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event): void {
                $event->roots[Renderer::TEMPLATE_ROOT] = $this->getBasePath() . '/templates/site';
            },
        );
    }

    /**
     * BR-17. Craft creates one Twig environment per view and template mode, each with its own
     * loader. Wrapping each as it's created covers site and CP modes, and any other view, without
     * touching the loader of an environment the plugin didn't see made.
     */
    private function registerLoader(): void
    {
        Event::on(
            View::class,
            View::EVENT_AFTER_CREATE_TWIG,
            function(CreateTwigEvent $event): void {
                $loader = $event->twig->getLoader();
                if (!$loader instanceof Loader) {
                    $event->twig->setLoader(new Loader($loader));
                }
            },
        );
    }

    /**
     * BR-14. `clear-caches/all` flushes the data cache, which holds the index, so it needs nothing.
     */
    private function registerCacheOption(): void
    {
        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function(RegisterCacheOptionsEvent $event): void {
                $event->options[] = [
                    'key' => Index::CACHE_TAG,
                    'label' => Craft::t('component-viewer', 'Component viewer index'),
                    'action' => fn() => $this->getIndex()->invalidate(),
                ];
            },
        );
    }

    /**
     * §4: Craft's garbage collection removes old links, and a deleted user's links go at once
     * (TN-13). Users are soft-deleted, so the table's cascade only fires when one is purged.
     */
    private function registerShareCleanup(): void
    {
        Event::on(Gc::class, Gc::EVENT_RUN, fn() => $this->getShares()->gc());

        Event::on(User::class, Element::EVENT_AFTER_DELETE, function(Event $event): void {
            /** @var User $user */
            $user = $event->sender;
            $this->getShares()->deleteForUser((int)$user->id);
        });
    }

    private function registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event): void {
                $nested = [
                    self::PERMISSION_MANAGE_SHARES => [
                        'label' => Craft::t('component-viewer', 'Create and revoke share links'),
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
