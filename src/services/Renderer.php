<?php

namespace webdna\componentlibrary\services;

use Craft;
use craft\elements\User;
use craft\helpers\UrlHelper;
use craft\models\Site;
use craft\web\View;
use DateInterval;
use DateTime;
use Throwable;
use Twig\Error\Error as TwigError;
use Twig\Markup;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\legacy\ConfigJsonAdapter;
use webdna\componentlibrary\models\Component;
use webdna\componentlibrary\models\Prop;
use webdna\componentlibrary\models\Story;
use yii\base\Component as BaseComponent;
use yii\base\Exception;
use yii\base\InvalidArgumentException;

/**
 * Renders one story of one component inside the site's preview layout (BR-20 to BR-26).
 *
 * Previews are anonymous site requests routed here by a Craft token, whose only parameter is the
 * scope: `user:<id>` or `share:<id>`. Everything else about a render (component, story, props)
 * comes from the query string, so it's treated as hostile: a handle is only ever an index key, a
 * story only a key of that component's stories, a prop only a declared prop's coerced value, and
 * the background only one of Component::BACKGROUNDS.
 *
 * @phpstan-type ErrorDetail array{message:string,template:string|null,line:int|null}
 */
class Renderer extends BaseComponent
{
    /** The controller action every preview token routes to. */
    public const ROUTE = 'component-library/render/index';

    public const SCOPE_PATTERN = '/^(user|share):([1-9][0-9]*)$/D';

    /**
     * The plugin's site template root, `src/templates/site`. It holds only `_render`, and Craft's
     * router never serves a template whose path below its root has a segment starting with `_`.
     */
    public const TEMPLATE_ROOT = 'component-library';

    /** The preview templates, `src/templates/site/_render`. */
    public const TEMPLATES = self::TEMPLATE_ROOT . '/_render';

    public const DEFAULT_LAYOUT = self::TEMPLATES . '/layout';

    /** The plugin's own preview stylesheet (BR-42), `src/web/assets/preview/dist`. */
    public const PREVIEW_CSS = 'preview.css';

    /** BR-24's limits. */
    public const MAX_PROPS_BYTES = 8192;
    public const MAX_STRING = 2000;
    public const MAX_TEXT = 10000;
    public const MAX_JSON_DEPTH = 5;

    /** BR-20: a token outlives the page that asked for it by at most an hour. */
    private const LIFETIME = 'PT1H';

    /** How deep legacy `{include:@h}` defaults may nest, which also stops a cycle. */
    private const INCLUDE_DEPTH = 3;

    /**
     * The preview layout (BR-25): a site template path, or a map of site handle to path. A site
     * missing from the map gets the plugin's bare layout.
     *
     * @var string|array<string,string>|null
     */
    public string|array|null $layout = null;

    /** The index components are looked up in. The plugin's own when unset. */
    public ?Index $index = null;

    /**
     * A Craft token for previews in one scope (BR-20), valid for an hour or until `$until`,
     * whichever comes first, with no usage limit.
     *
     * @param string $scope `user:<id>` or `share:<id>`
     * @param DateTime|null $until A share's expiry
     */
    public function createToken(string $scope, ?DateTime $until = null): string
    {
        if (!preg_match(self::SCOPE_PATTERN, $scope)) {
            throw new InvalidArgumentException("Invalid preview scope \"$scope\"");
        }

        $expiry = (new DateTime())->add(new DateInterval(self::LIFETIME));
        if ($until !== null && $until < $expiry) {
            $expiry = $until;
        }

        $token = Craft::$app->getTokens()->createToken([self::ROUTE, ['scope' => $scope]], null, $expiry);
        if ($token === false) {
            throw new Exception('Couldn’t create a preview token.');
        }

        return $token;
    }

    /**
     * The iframe address for a preview on `$site` (BR-20). The site only picks the base URL: the
     * render itself uses whichever site serves the request (BR-22).
     *
     * @param array<string,mixed>|null $props Request props, sent as JSON
     * @param string|null $bg A background other than the component's own (BR-41), from background()
     */
    public function previewUrl(Site $site, string $token, string $handle, ?string $story = null, ?array $props = null, ?string $bg = null): string
    {
        $params = array_filter([
            Craft::$app->getConfig()->getGeneral()->tokenParam => $token,
            'component' => $handle,
            'story' => $story,
            'props' => $props ? json_encode($props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'bg' => $bg,
        ], fn($value) => $value !== null);

        return rtrim((string)$site->getBaseUrl(), '/') . '/?' . http_build_query($params);
    }

    /**
     * BR-21: whether a scope may still render, rechecked on every request. A user must exist, be
     * active and hold the view permission. A share must be neither cancelled nor expired.
     */
    public function scopeIsValid(string $scope): bool
    {
        if (!preg_match(self::SCOPE_PATTERN, $scope, $match)) {
            return false;
        }

        $id = (int)$match[2];

        if ($match[1] === 'user') {
            $user = Craft::$app->getUsers()->getUserById($id);

            return $user !== null
                && $user->getStatus() === User::STATUS_ACTIVE
                && $user->can(ComponentLibrary::PERMISSION_VIEW);
        }

        return ComponentLibrary::getInstance()->getShares()->active()->andWhere(['id' => $id])->exists();
    }

    /**
     * BR-41, BR-42: the background in effect. A request value counts only when it's exactly one
     * of Component::BACKGROUNDS. Anything else, or nothing, is the component's own `background`.
     * The value returned is always the constant's own string, never the request's.
     */
    public static function background(Component $component, mixed $requested = null): string
    {
        foreach (Component::BACKGROUNDS as $background) {
            if ($requested === $background) {
                return $background;
            }
        }

        return $component->background ?? Component::BACKGROUNDS[0];
    }

    /** Whether errors in this scope may show their detail (BR-26). */
    public static function isUserScope(string $scope): bool
    {
        return str_starts_with($scope, 'user:');
    }

    /**
     * BR-24: the `props` query parameter as a hash. Missing or empty is no props.
     *
     * @return array<int|string,mixed>
     * @throws InvalidArgumentException if it's over 8 KB, or not a JSON object
     */
    public function decodeProps(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (!is_string($raw) || strlen($raw) > self::MAX_PROPS_BYTES) {
            throw new InvalidArgumentException(sprintf('props must be a JSON object of at most %d KB', self::MAX_PROPS_BYTES / 1024));
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new InvalidArgumentException('props must be a JSON object');
        }

        return $decoded;
    }

    /**
     * The props a story renders with: prop defaults, then the story's own, then the request's
     * (BR-24). A request value for an undeclared or code-only (`control: false`) prop, or one
     * that doesn't coerce to its prop's type, is dropped, and the story's value stands.
     *
     * @param array<int|string,mixed> $request Decoded by decodeProps()
     * @return array<string,mixed>
     */
    public function props(Component $component, Story $story, array $request): array
    {
        $props = $this->fileProps($component, $story, [(string)$component->handle]);

        foreach ($request as $name => $value) {
            $prop = $component->props[$name] ?? null;
            if ($prop !== null && $prop->control && ($coerced = $this->coerce($prop, $value)) !== null) {
                $props[$prop->name] = $coerced[0];
            }
        }

        return $props;
    }

    /**
     * The whole preview page: the story, inside the configured layout's `component` block, with
     * the component's `viewClass` in block `viewClass` (BR-25). Always site template mode.
     *
     * On a `light` or `dark` background the story sits in the canvas wrapper, after a link to
     * preview.css. On `site` the page is exactly as it was before backgrounds existed (BR-42).
     *
     * @param array<string,mixed> $props From props()
     * @param string $background From background()
     */
    public function render(Component $component, Story $story, array $props, string $background = 'site'): string
    {
        $view = Craft::$app->getView();
        $mode = $view->getTemplateMode();
        $level = ob_get_level();

        try {
            $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

            // Rendered before the layout, so a broken component fails before any layout output.
            $html = $this->renderStory($component, $story, $props);

            return $view->renderPageTemplate(self::TEMPLATES . '/page', [
                'clLayout' => $this->layout(),
                'clHtml' => new Markup($html, 'UTF-8'),
                'clViewClass' => $component->viewClass,
                'clCanvas' => $background === Component::BACKGROUNDS[0] ? null : $background,
                'clPreviewCss' => $background === Component::BACKGROUNDS[0] ? null : $this->previewCssUrl(),
            ], View::TEMPLATE_MODE_SITE);
        } catch (Throwable $e) {
            // Craft's page render opens an output buffer it doesn't close when a layout throws.
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        } finally {
            $view->setTemplateMode($mode);
        }
    }

    /**
     * The error panel page (BR-21, BR-26). Detail, when given, has been through describe().
     *
     * @param ErrorDetail|null $detail
     */
    public function errorPage(string $message, ?array $detail = null): string
    {
        return Craft::$app->getView()->renderPageTemplate(self::TEMPLATES . '/error', [
            'message' => $message,
            'detail' => $detail,
        ], View::TEMPLATE_MODE_SITE);
    }

    /**
     * What a user-scope error panel shows: the message, the template name and the line, with every
     * absolute server path taken out (BR-26).
     *
     * @return ErrorDetail
     */
    public function describe(Throwable $e): array
    {
        $message = $e->getMessage();
        $template = null;
        $line = null;

        if ($e instanceof TwigError) {
            $message = $e->getRawMessage();
            $template = $e->getSourceContext()?->getName();
            $line = $e->getTemplateLine() > 0 ? $e->getTemplateLine() : null;
        }

        return [
            'message' => $this->scrub($message),
            'template' => $template === null ? null : $this->scrub($template),
            'line' => $line,
        ];
    }

    /** The layout for the current site (BR-25). */
    public function layout(): string
    {
        $layout = is_array($this->layout)
            ? ($this->layout[Craft::$app->getSites()->getCurrentSite()->handle] ?? null)
            : $this->layout;

        return $layout ?: self::DEFAULT_LAYOUT;
    }

    /**
     * BR-26's headers, for every render response whatever its status.
     *
     * @return array<string,string>
     */
    public function headers(): array
    {
        $primary = Craft::$app->getSites()->getPrimarySite()->getBaseUrl();
        $origins = array_unique(array_filter([
            self::origin(UrlHelper::cpUrl()),
            $primary === null ? null : self::origin($primary),
        ]));

        return [
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => 'frame-ancestors ' . ($origins ? implode(' ', $origins) : "'none'"),
        ];
    }

    /** The published preview.css (BR-42). Not an asset bundle: the layout's head isn't ours to add to. */
    public function previewCssUrl(): string
    {
        $dir = ComponentLibrary::getInstance()->getBasePath() . '/web/assets/preview/dist';

        return (string)Craft::$app->getAssetManager()->getPublishedUrl($dir, true, self::PREVIEW_CSS);
    }

    private function index(): Index
    {
        return $this->index ?? ComponentLibrary::getInstance()->getIndex();
    }

    /**
     * Props from files only: defaults, then the story's. In a legacy component these may hold
     * `{include:@h}`, which renders here (BR-16). No request value ever reaches this.
     *
     * @param list<string> $stack Handles being included, outermost first
     * @return array<string,mixed>
     */
    private function fileProps(Component $component, Story $story, array $stack): array
    {
        $props = array_merge(array_map(fn(Prop $prop) => $prop->default, $component->props), $story->props);

        return $component->configPath === null ? $props : $this->includes($props, $stack);
    }

    /**
     * @param array<int|string,mixed> $values
     * @param list<string> $stack
     * @return array<int|string,mixed>
     */
    private function includes(array $values, array $stack): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->includes($value, $stack);
            } elseif (is_string($value) && preg_match(ConfigJsonAdapter::INCLUDE_PATTERN, $value)) {
                $values[$key] = new Markup((string)preg_replace_callback(
                    ConfigJsonAdapter::INCLUDE_PATTERN,
                    fn(array $match) => $this->include($match[1], $stack) ?? $match[0],
                    $value,
                ), 'UTF-8');
            }
        }

        return $values;
    }

    /**
     * One `{include:@h}`: the handle's first story, as v1 rendered it with the component's own
     * defaults. An unknown handle, a cycle or too deep a nesting stays literal.
     *
     * @param list<string> $stack
     */
    private function include(string $handle, array $stack): ?string
    {
        $component = $this->index()->get($handle);
        if ($component === null || in_array($handle, $stack, true) || count($stack) >= self::INCLUDE_DEPTH) {
            return null;
        }

        $story = $component->stories[array_key_first($component->stories)] ?? Story::fromDefaults($component);
        $props = $this->fileProps($component, $story, [...$stack, $handle]);

        return Craft::$app->getView()->renderTemplate($handle, $props, View::TEMPLATE_MODE_SITE);
    }

    /**
     * @param array<string,mixed> $props
     */
    private function renderStory(Component $component, Story $story, array $props): string
    {
        $view = Craft::$app->getView();
        $handle = (string)$component->handle;

        if ($story->block === null || $component->storiesPath === null || $component->root === null) {
            return $story->render($view->getTwig(), $handle, null, $props);
        }

        // Craft refuses absolute template names. A stories file under the site templates folder
        // renders by its name there, and one in a root outside it has that root as the templates
        // path for the render, the same way the legacy adapter reads configs (BR-15).
        $site = Craft::$app->getPath()->getSiteTemplatesPath();
        [$base, $name] = str_starts_with($component->storiesPath, "$site/")
            ? [$site, substr($component->storiesPath, strlen($site) + 1)]
            : [$component->root, substr($component->storiesPath, strlen($component->root) + 1)];

        $templatesPath = $view->getTemplatesPath();
        try {
            $view->setTemplatesPath($base);

            return $story->render($view->getTwig(), $handle, $name, $props);
        } finally {
            $view->setTemplatesPath($templatesPath);
        }
    }

    /**
     * BR-24's coercion. Null means the value is refused.
     *
     * @return array{0:mixed}|null
     */
    private function coerce(Prop $prop, mixed $value): ?array
    {
        return match ($prop->type) {
            'string' => self::text($value, self::MAX_STRING),
            'text' => self::text($value, self::MAX_TEXT),
            'bool' => is_bool($value) || is_int($value) || is_string($value)
                ? self::wrap(filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE))
                : null,
            'number' => is_int($value) || is_float($value) ? [$value] : (is_string($value)
                ? self::wrap(filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE))
                : null),
            'select' => self::option($prop, $value),
            // BR-44: exactly one of the names, which come from the icons folder, never the request.
            'icon' => is_string($value) && in_array($value, $this->index()->iconNames(), true) ? [$value] : null,
            'json' => self::depth($value) <= self::MAX_JSON_DEPTH ? [self::inert($value)] : null,
            default => null,
        };
    }

    /**
     * @return array{0:mixed}|null
     */
    private static function wrap(mixed $value): ?array
    {
        return $value === null ? null : [$value];
    }

    /**
     * @return array{0:Markup}|null
     */
    private static function text(mixed $value, int $max): ?array
    {
        if (is_int($value) || is_float($value)) {
            $value = (string)$value;
        }

        return is_string($value) && mb_strlen($value) <= $max ? [self::escaped($value)] : null;
    }

    /**
     * An option's value exactly as the file declares it, never the request's copy.
     *
     * @return array{0:int|string}|null
     */
    private static function option(Prop $prop, mixed $value): ?array
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        foreach (array_keys($prop->options) as $option) {
            if ((string)$option === (string)$value) {
                return [$option];
            }
        }

        return null;
    }

    private static function depth(mixed $value): int
    {
        return is_array($value) ? 1 + max([0, ...array_map(self::depth(...), array_values($value))]) : 0;
    }

    /**
     * Every string in a request value as pre-escaped Markup, so `|raw` prints it as text (BR-24).
     * A key can't be Markup, so a key that escaping would change is dropped.
     */
    private static function inert(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::escaped($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        $inert = [];
        foreach ($value as $key => $item) {
            if (is_int($key) || htmlspecialchars($key, ENT_QUOTES) === $key) {
                $inert[$key] = self::inert($item);
            }
        }

        return $inert;
    }

    private static function escaped(string $value): Markup
    {
        return new Markup(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'UTF-8');
    }

    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (!isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * Takes every server path out of an error message: the known folders become relative, and
     * any other absolute directory is elided.
     */
    private function scrub(string $text): string
    {
        $prefixes = array_filter([
            Craft::$app->getPath()->getSiteTemplatesPath(),
            Craft::$app->getPath()->getStoragePath(),
            ComponentLibrary::getInstance()->getBasePath(),
            (string)Craft::getAlias('@vendor'),
            (string)Craft::getAlias('@root'),
            ...$this->index()->roots(),
        ], fn(string $prefix) => $prefix !== '' && $prefix !== '/');
        usort($prefixes, fn(string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($prefixes as $prefix) {
            $text = str_replace([rtrim($prefix, '/') . '/', rtrim($prefix, '/')], '', $text);
        }

        return (string)preg_replace('~(?<![\w:/.])/(?:[^\s/"\'“”()]+/)+~u', '…/', $text);
    }
}
