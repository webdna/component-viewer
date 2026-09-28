<?php

namespace webdna\componentlibrary\services;

use Craft;
use craft\helpers\FileHelper;
use craft\web\View;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Twig\Environment;
use Twig\Error\Error as TwigError;
use Twig\Source;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\models\Component;
use webdna\componentlibrary\models\Story;
use webdna\componentlibrary\twig\ComponentNode;
use webdna\componentlibrary\twig\StoryNode;
use webdna\componentlibrary\twig\StoryTokenParser;
use yii\base\Component as BaseComponent;
use yii\caching\TagDependency;

/**
 * The component index for the current site: handle → component, with its file, metadata, props
 * and stories (BR-11 to BR-14).
 *
 * It's built from the template files, never from the request, and held in Craft's data cache, so a
 * warm lookup reads one cache entry per request and touches no directory (BR-33). With devMode on,
 * each request makes one stat walk of the roots and rebuilds when a file changed.
 *
 * @phpstan-type Walk list<array{root:string,files:array<string,array{int,int}>}>
 */
class Index extends BaseComponent
{
    /** Invalidated by the *Component library index* Clear Caches option. */
    public const CACHE_TAG = 'component-library-index';

    /** BR-12's pre-filter: only a file that might hold a component tag is parsed. */
    private const TAG_PATTERN = '/\{%-?\s*component\b/';

    /**
     * Roots in precedence order, lowest first (BR-11). Aliases allowed.
     *
     * @var string[]
     */
    public array $templateDirectories = ['@templates/_components'];

    /** The folder holding one sub-folder per site handle, if site versions are used (BR-11). */
    public ?string $sites = null;

    /** Test hook: index builds since the last reset(). */
    public int $builds = 0;

    /** Test hook: directory iterators constructed since the last reset(). */
    public int $walks = 0;

    /** @var array<string,array<string,Component>> This request's indexes, by cache key */
    private array $loaded = [];

    /**
     * Every component on the current site, by handle, sorted.
     *
     * @return array<string,Component>
     */
    public function all(): array
    {
        $roots = $this->roots();
        $key = [self::class, Craft::$app->getSites()->getCurrentSite()->handle, ComponentLibrary::getInstance()->schemaVersion, $roots];
        $memo = md5(serialize($key));

        return $this->loaded[$memo] ??= $this->load($key, $roots);
    }

    public function get(string $handle): ?Component
    {
        return $this->all()[$handle] ?? null;
    }

    /**
     * Drops every site's cached index (the Clear Caches option).
     */
    public function invalidate(): void
    {
        TagDependency::invalidate(Craft::$app->getCache(), self::CACHE_TAG);
        $this->loaded = [];
    }

    /**
     * Forgets this request's lookups and zeroes the counters, as a new request would. For tests
     * and long-running processes.
     */
    public function reset(): void
    {
        $this->loaded = [];
        $this->builds = 0;
        $this->walks = 0;
    }

    /**
     * The configured roots as absolute paths, in precedence order (BR-11), whether or not they
     * exist. The site folder is always the current site's, and always last.
     *
     * @return list<string>
     */
    public function roots(): array
    {
        $roots = array_map(fn(string $dir) => $this->absolute($dir), $this->templateDirectories);

        if ($this->sites !== null) {
            $roots[] = $this->absolute($this->sites) . '/' . Craft::$app->getSites()->getCurrentSite()->handle;
        }

        return array_values(array_unique($roots));
    }

    /**
     * BR-13's handle for a file without one of its own: path segments joined with `:`, extension
     * dropped, and a stem equal to its folder collapsed.
     *
     * @param string $path Relative to its root, with `/` separators
     */
    public static function handleFor(string $path): string
    {
        $segments = explode('/', preg_replace('/\.twig$/', '', $path));
        $count = count($segments);
        if ($count > 1 && $segments[$count - 1] === $segments[$count - 2]) {
            array_pop($segments);
        }

        return '@' . implode(':', $segments);
    }

    /**
     * @param array<int,mixed> $key
     * @param list<string> $roots
     * @return array<string,Component>
     */
    private function load(array $key, array $roots): array
    {
        $cache = Craft::$app->getCache();
        $cached = $cache->get($key);
        $walk = null;

        if ($cached !== false && Craft::$app->getConfig()->getGeneral()->devMode) {
            $walk = $this->walk($roots);
            if ($cached['fingerprint'] !== md5(serialize($walk))) {
                $cached = false;
            }
        }

        if ($cached === false) {
            $walk ??= $this->walk($roots);
            $cached = ['fingerprint' => md5(serialize($walk)), 'components' => $this->build($walk)];
            $cache->set($key, $cached, null, new TagDependency(['tags' => [self::CACHE_TAG]]));
        }

        return $cached['components'];
    }

    /**
     * Every file under the roots that exist, with its mtime and size. Also the devMode fingerprint,
     * so a check and the rebuild it triggers share one walk.
     *
     * The sites folder is pruned from the other roots, so a site version is never indexed a
     * second time under a handle like `@_sites:second:ui:button` when it sits inside one.
     *
     * @param list<string> $roots
     * @return Walk
     */
    private function walk(array $roots): array
    {
        $sites = $this->sites !== null ? $this->absolute($this->sites) : null;
        $walk = [];

        foreach ($roots as $root) {
            $isSiteFolder = $sites !== null && str_starts_with($root, "$sites/");
            if (!is_dir($root)) {
                // A site without versions has no folder, which is normal. A configured root is not.
                $isSiteFolder
                    ? Craft::info("No site versions at $root.", __METHOD__)
                    : Craft::warning("Component library root $root doesn't exist, so it was skipped.", __METHOD__);
                continue;
            }

            $this->walks++;
            $prune = $isSiteFolder ? null : $sites;
            $dirs = new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
                fn(SplFileInfo $file) => !$file->isDir() || $file->getPathname() !== $prune,
            );

            $files = [];
            foreach (new RecursiveIteratorIterator($dirs) as $file) {
                /** @var SplFileInfo $file */
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
                $files[$relative] = [$file->getMTime(), $file->getSize()];
            }
            ksort($files, SORT_STRING);

            $walk[] = ['root' => $root, 'files' => $files];
        }

        return $walk;
    }

    /**
     * @param Walk $walk
     * @return array<string,Component>
     */
    private function build(array $walk): array
    {
        $this->builds++;

        // Parsed with the site Twig environment, where front-end components are compiled.
        $view = Craft::$app->getView();
        $mode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        try {
            $twig = $view->getTwig();
            $index = [];

            foreach ($walk as ['root' => $root, 'files' => $files]) {
                // Within a root, the first file by sorted path keeps a handle (BR-13).
                $found = [];
                foreach (array_keys($files) as $relative) {
                    $component = $this->read($twig, $root, $relative, $files);
                    if ($component === null) {
                        continue;
                    }

                    $handle = $component->handle;
                    if (isset($found[$handle])) {
                        $found[$handle] = $found[$handle]->with(['duplicates' => [...$found[$handle]->duplicates, $component->path]]);
                    } else {
                        $found[$handle] = $component;
                    }
                }

                // Across roots, the later root wins (BR-13).
                foreach ($found as $handle => $component) {
                    $index[$handle] = isset($index[$handle]) ? $component->with(['overrides' => $index[$handle]->path]) : $component;
                }
            }
        } finally {
            $view->setTemplateMode($mode);
        }

        ksort($index, SORT_STRING);

        return $index;
    }

    /**
     * The component a file declares, or null if it isn't one. A file whose tag doesn't parse is
     * still a component, with the error recorded, so one bad file never breaks the library.
     *
     * @param array<string,mixed> $files The root's files, to find the stories file beside it
     */
    private function read(Environment $twig, string $root, string $relative, array $files): ?Component
    {
        if (!str_ends_with($relative, '.twig') || str_ends_with($relative, StoryTokenParser::FILE_SUFFIX)) {
            return null;
        }

        $path = "$root/$relative";
        $code = (string)file_get_contents($path);
        if (!preg_match(self::TAG_PATTERN, $code)) {
            return null;
        }

        $errors = [];
        try {
            $component = ComponentNode::find($twig->parse($twig->tokenize(new Source($code, $relative, $path))))?->getComponent();
            if ($component === null) {
                // The pattern matched something that isn't a tag, such as a comment.
                return null;
            }
        } catch (TwigError $e) {
            $component = new Component();
            $errors[] = self::error($path, $e);
        }

        $handle = $component->handle ?? self::handleFor($relative);
        if (!preg_match(Component::HANDLE_PATTERN, $handle)) {
            $errors[] = [
                'path' => $path,
                'line' => null,
                'message' => "Its path gives the handle $handle, which templates can't include. Move it into a category folder or give its tag a handle.",
            ];
        }

        $stories = [];
        $storiesPath = null;
        $storiesRelative = substr($relative, 0, -strlen('.twig')) . StoryTokenParser::FILE_SUFFIX;
        if (isset($files[$storiesRelative])) {
            $storiesPath = "$root/$storiesRelative";
            try {
                $stories = StoryNode::findAll($twig->parse($twig->tokenize(new Source((string)file_get_contents($storiesPath), $storiesRelative, $storiesPath))));
            } catch (TwigError $e) {
                $errors[] = self::error($storiesPath, $e);
            }
        }

        // BR-10, and the fallback for a stories file that declares none or doesn't parse.
        if ($stories === []) {
            $default = Story::fromDefaults($component);
            $stories = [$default->name => $default];
        }

        return $component->with([
            'handle' => $handle,
            'path' => $path,
            'root' => $root,
            'stories' => $stories,
            'storiesPath' => $storiesPath,
            'errors' => $errors,
        ]);
    }

    /**
     * @return array{path:string,line:int|null,message:string}
     */
    private static function error(string $path, TwigError $e): array
    {
        $line = $e->getTemplateLine();

        return ['path' => $path, 'line' => $line > 0 ? $line : null, 'message' => $e->getRawMessage()];
    }

    private function absolute(string $dir): string
    {
        return FileHelper::normalizePath(Craft::getAlias($dir, false) ?: $dir);
    }
}
