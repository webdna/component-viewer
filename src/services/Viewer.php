<?php

namespace webdna\componentlibrary\services;

use Closure;
use Craft;
use craft\models\Site;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\models\Component;
use webdna\componentlibrary\models\Prop;
use webdna\componentlibrary\models\Story;
use yii\base\Component as BaseComponent;
use yii\base\InvalidArgumentException;

/**
 * What the viewer shows for one request: the tree, the chosen component, story and settings, and
 * the preview address (§6 Screens). The CP viewer uses it now, and the share viewer (task 4.2)
 * with a share-scoped token and no paths (BR-29).
 *
 * Every request value is checked against something the server already holds: the site against
 * getSiteByHandle() (BR-22), the handle against the index, the story against the component's
 * stories and each prop against its declarations. Anything else is ignored.
 *
 * @phpstan-type Control array{prop:Prop,value:mixed}
 * @phpstan-type State array{
 *     site:Site,
 *     sites:list<Site>,
 *     tree:array<string,list<Component>>,
 *     links:array<string,string>,
 *     siteForm:array{action:string,hidden:array<string,string>}|null,
 *     component:Component|null,
 *     story:Story|null,
 *     controls:list<Control>,
 *     overrides:array<string,mixed>,
 *     previewUrl:string|null,
 *     siteVersion:bool,
 *     sources:list<array{label:string,path:string,source:string}>,
 *     roots:list<string>,
 *     config:array<string,mixed>|null,
 * }
 */
class Viewer extends BaseComponent
{
    /** The format guide the empty state links to (written by task 5.3). */
    public const FORMAT_GUIDE = 'https://github.com/webdna/component-viewer/blob/main/docs/format.md';

    /** The index components are listed from. The plugin's own when unset. */
    public ?Index $index = null;

    /**
     * The sites the site switch offers. Only one hides it (BR-35).
     *
     * @return list<Site>
     */
    public function sites(): array
    {
        return array_values(Craft::$app->getSites()->getAllSites());
    }

    /**
     * BR-22: the site the preview is served from. A handle counts only once Craft has returned a
     * site for it, and anything else is the primary site.
     */
    public function site(mixed $handle): Site
    {
        $site = is_string($handle) && $handle !== '' ? Craft::$app->getSites()->getSiteByHandle($handle) : null;

        return $site ?? Craft::$app->getSites()->getPrimarySite();
    }

    /**
     * Everything the viewer templates need, or null when `$handle` names no component on `$site`.
     *
     * @param string|null $handle The component asked for, or null for the first one
     * @param mixed $story The requested story name. An unknown one gets the first story.
     * @param mixed $props The raw `props` parameter
     * @param string $token A preview token (Renderer::createToken()) for the preview's scope
     * @param Closure(string,array<string,string>):string $url The viewer's address for a handle
     * and query params. Links always pass `site`, because Craft's cpUrl() adds the requested
     * site to any CP URL that doesn't.
     * @return State|null
     */
    public function state(Site $site, ?string $handle, mixed $story, mixed $props, string $token, Closure $url): ?array
    {
        $components = $this->components($site);

        if ($handle === null) {
            $component = $components[array_key_first($components)] ?? null;
        } elseif (!($component = $components[$handle] ?? null)) {
            return null;
        }

        $state = [
            'site' => $site,
            'sites' => $this->sites(),
            'tree' => $this->tree($components),
            'links' => array_map(fn(string $h) => $url($h, ['site' => $site->handle]), array_combine(array_keys($components), array_keys($components))),
            'siteForm' => null,
            'component' => $component,
            'story' => null,
            'controls' => [],
            'overrides' => [],
            'previewUrl' => null,
            'siteVersion' => false,
            'sources' => [],
            'roots' => array_map(self::relative(...), $this->index()->roots($site->handle)),
            'config' => null,
        ];

        if ($component === null) {
            return $state;
        }

        $current = $this->story($component, $story);
        $overrides = $this->overrides($component, $props);
        $previewUrl = ComponentLibrary::getInstance()->getRenderer()
            ->previewUrl($site, $token, (string)$component->handle, $current->name, $overrides ?: null);

        return array_merge($state, [
            'story' => $current,
            'siteForm' => self::formFor($url((string)$component->handle, [])),
            'controls' => $this->controls($component, $current, $overrides),
            'overrides' => $overrides,
            'previewUrl' => $previewUrl,
            'siteVersion' => $this->isSiteVersion($component, $site),
            'sources' => $this->sources($component),
            'config' => [
                'handle' => $component->handle,
                'name' => $component->name ?? $component->handle,
                'story' => $current->name,
                'stories' => array_map(fn(Story $s) => $this->fileValues($component, $s), $component->stories),
                'types' => array_map(fn(Prop $prop) => $prop->type, $component->props),
                'overrides' => (object)$overrides,
                'previewUrl' => $previewUrl,
            ],
        ]);
    }

    /**
     * The components on `$site`, by handle. The index always reads Craft's current site (BR-11),
     * so that's `$site` for the lookup only.
     *
     * @return array<string,Component>
     */
    public function components(Site $site): array
    {
        $sites = Craft::$app->getSites();
        $current = $sites->getCurrentSite();

        try {
            $sites->setCurrentSite($site);

            return $this->index()->all();
        } finally {
            $sites->setCurrentSite($current);
        }
    }

    /**
     * Components by category: every handle segment but the last (`@ui:button` → `ui`).
     *
     * @param array<string,Component> $components
     * @return array<string,list<Component>>
     */
    public function tree(array $components): array
    {
        $tree = [];
        foreach ($components as $handle => $component) {
            $segments = explode(':', ltrim($handle, '@'));
            array_pop($segments);
            $tree[implode(':', $segments)][] = $component;
        }
        ksort($tree);

        return $tree;
    }

    /** The story asked for, else the first. The index gives every component at least one. */
    public function story(Component $component, mixed $name): Story
    {
        $story = is_string($name) ? ($component->stories[$name] ?? null) : null;

        return $story ?? $component->stories[array_key_first($component->stories)] ?? Story::fromDefaults($component);
    }

    /**
     * The request's settings for declared props only. A `props` value the renderer would refuse
     * (over 8 KB, not a JSON object) counts as none. Values aren't coerced here: the renderer
     * does that for the preview (BR-24), and the controls are only ever escaped output.
     *
     * @return array<string,mixed>
     */
    public function overrides(Component $component, mixed $raw): array
    {
        try {
            $decoded = ComponentLibrary::getInstance()->getRenderer()->decodeProps($raw);
        } catch (InvalidArgumentException) {
            return [];
        }

        return array_intersect_key($decoded, $component->props);
    }

    /**
     * One control per declared prop, holding the value the preview starts with.
     *
     * @param array<string,mixed> $overrides
     * @return list<Control>
     */
    public function controls(Component $component, Story $story, array $overrides): array
    {
        $values = array_merge($this->fileValues($component, $story), $overrides);

        return array_values(array_map(
            fn(Prop $prop) => ['prop' => $prop, 'value' => self::controlValue($prop, $values[$prop->name] ?? null)],
            $component->props,
        ));
    }

    /**
     * The site's own version of a component, from its site folder (BR-11, BR-13), is marked.
     */
    public function isSiteVersion(Component $component, Site $site): bool
    {
        $sites = $this->index()->sitesFolder();

        return $sites !== null && $component->root === "$sites/$site->handle";
    }

    /**
     * The files a component is read from, for the Source tab. `path` is relative to the project
     * root, for the CP only: the share viewer never shows it (BR-29).
     *
     * @return list<array{label:string,path:string,source:string}>
     */
    public function sources(Component $component): array
    {
        $files = array_filter([
            Craft::t('component-library', 'Component') => $component->path,
            Craft::t('component-library', 'Examples') => $component->storiesPath,
            Craft::t('component-library', 'Legacy settings') => $component->configPath,
        ]);

        $sources = [];
        foreach ($files as $label => $path) {
            $source = is_file($path) ? file_get_contents($path) : false;
            if ($source !== false) {
                $sources[] = ['label' => $label, 'path' => self::relative($path), 'source' => $source];
            }
        }

        return $sources;
    }

    /**
     * A GET form for `$url`. Browsers drop the query of a GET form's action, so it's split into
     * the path and hidden fields (e.g. `p` without pretty URLs). The form's own fields replace
     * `site`, `story` and `props`.
     *
     * @return array{action:string,hidden:array<string,string>}
     */
    public static function formFor(string $url): array
    {
        [$action, $query] = array_pad(explode('?', $url, 2), 2, '');
        parse_str($query, $hidden);

        return [
            'action' => $action,
            'hidden' => array_filter(
                array_diff_key($hidden, ['site' => 1, 'story' => 1, 'props' => 1]),
                fn($value) => is_string($value),
            ),
        ];
    }

    /** A path relative to the project root, for display in the CP. */
    public static function relative(string $path): string
    {
        $root = rtrim((string)Craft::getAlias('@root'), '/') . '/';

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }

    private function index(): Index
    {
        return $this->index ?? ComponentLibrary::getInstance()->getIndex();
    }

    /**
     * A story's values for its declared props, from files only: defaults, then the story's own.
     *
     * @return array<string,mixed>
     */
    private function fileValues(Component $component, Story $story): array
    {
        $defaults = array_map(fn(Prop $prop) => $prop->default, $component->props);

        return array_intersect_key(array_merge($defaults, $story->props), $component->props);
    }

    /**
     * A value as its control shows it: text for text inputs, a bool for a checkbox, JSON for
     * `json`. A value of the wrong shape shows as empty.
     */
    private static function controlValue(Prop $prop, mixed $value): mixed
    {
        return match ($prop->type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            'json' => $value === null ? '' : (string)json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default => is_scalar($value) ? (string)$value : '',
        };
    }
}
