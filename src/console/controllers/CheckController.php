<?php

namespace webdna\componentlibrary\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\FileHelper;
use craft\web\View;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Twig\Environment;
use Twig\Error\Error as TwigError;
use Twig\Node\EmbedNode;
use Twig\Node\Expression\Binary\ConcatBinary;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\IncludeNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Source;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\models\Component;
use webdna\componentlibrary\twig\Loader;
use webdna\componentlibrary\twig\StoryTokenParser;
use yii\console\ExitCode;

/**
 * The library check (BR-31): every problem the index recorded, plus every literal reference to a
 * handle no site has, one line each, then the count.
 *
 * ```
 * craft component-library/check [--strict]
 * ```
 *
 * Handles are checked against every site's index together, since a template may include a
 * component that only one site has. A reference marked `ignore missing` is never reported (BR-17).
 *
 * @phpstan-import-type Problem from Component
 */
class CheckController extends Controller
{
    /** Codes that fail the check on their own. The rest are warnings, which fail it only with --strict. */
    public const ERRORS = ['CL001', 'CL002', 'CL003', 'CL004', 'CL005'];

    public $defaultAction = 'index';

    /** Fail on warnings too. */
    public bool $strict = false;

    /** @var array<string,true> Every handle on any site, for the reference scan */
    private array $handles = [];

    /** @var list<Problem> */
    private array $found = [];

    /** @var array<int,true> Indexes of `embed … ignore missing` in the file being scanned */
    private array $ignoredEmbeds = [];

    public function options($actionID): array
    {
        return [...parent::options($actionID), 'strict'];
    }

    /**
     * Lists the library's problems. Exits 1 on any error, or on any warning with --strict.
     */
    public function actionIndex(): int
    {
        $problems = $this->problems();

        foreach ($problems as $problem) {
            $this->stdout(sprintf("%s %s %s\n", $problem['code'], $this->location($problem), $problem['message']));
        }
        $this->stdout(count($problems) . " problems\n");

        foreach ($problems as $problem) {
            if ($this->strict || in_array($problem['code'], self::ERRORS, true)) {
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        return ExitCode::OK;
    }

    /**
     * Every problem, from a fresh index of each site, sorted by path and line.
     *
     * @return list<Problem>
     */
    public function problems(): array
    {
        $index = ComponentLibrary::getInstance()->getIndex();
        $sites = Craft::$app->getSites();
        $current = $sites->getCurrentSite();
        $this->handles = [];
        $this->found = [];
        $folders = [FileHelper::normalizePath(Craft::getAlias('@templates'))];

        // The check reports what's on disk, not what a cache remembers.
        $index->invalidate();

        try {
            foreach ($sites->getAllSites(true) as $site) {
                $sites->setCurrentSite($site);
                foreach ($index->all() as $handle => $component) {
                    $this->handles[$handle] = true;
                    array_push($this->found, ...$component->errors, ...$component->warnings);
                    foreach ($component->duplicates as $path) {
                        $this->found[] = [
                            'code' => 'CL002',
                            'path' => $path,
                            'line' => null,
                            'message' => sprintf('Also claims %s, which %s already has, so this file is ignored. Give one of them another handle.', $handle, $this->display((string)$component->path)),
                        ];
                    }
                }
                array_push($folders, ...$index->roots());
            }

            $this->scanReferences(array_values(array_unique($folders)));
        } finally {
            $sites->setCurrentSite($current);
        }

        // A component every site shares reports once, not once per site.
        $problems = array_values(array_unique(array_map('serialize', $this->found)));
        $problems = array_map(fn(string $problem) => unserialize($problem), $problems);
        usort($problems, fn(array $a, array $b) => [$this->display($a['path']), $a['line'] ?? 0, $a['code']] <=> [$this->display($b['path']), $b['line'] ?? 0, $b['code']]);

        return $problems;
    }

    /**
     * Parses every template under the folders once, and reports the literal references to handles
     * that no site has (CL003, and CL004 in a stories file) and the names built at run time that
     * might be handles (CL008).
     *
     * @param list<string> $folders
     */
    private function scanReferences(array $folders): void
    {
        $extensions = Craft::$app->getConfig()->getGeneral()->defaultTemplateExtensions;
        $seen = [];

        // Parsed like the index, with the site Twig environment.
        $view = Craft::$app->getView();
        $mode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        try {
            $twig = $view->getTwig();
            foreach ($folders as $folder) {
                if (!is_dir($folder)) {
                    continue;
                }

                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                    /** @var SplFileInfo $file */
                    $path = FileHelper::normalizePath($file->getPathname());
                    if (isset($seen[$path]) || !in_array($file->getExtension(), $extensions, true)) {
                        continue;
                    }
                    $seen[$path] = true;
                    $this->scanFile($twig, $path, substr($path, strlen($folder) + 1));
                }
            }
        } finally {
            $view->setTemplateMode($mode);
        }
    }

    private function scanFile(Environment $twig, string $path, string $relative): void
    {
        try {
            $module = $twig->parse($twig->tokenize(new Source((string)file_get_contents($path), $relative, $path)));
        } catch (TwigError $e) {
            // A component's own parse error is already CL001. Any other template that doesn't
            // parse here, such as one using another project's tag, isn't the library's to report.
            Craft::info("Skipped $path, which doesn't parse: {$e->getMessage()}", __METHOD__);

            return;
        }

        $this->ignoredEmbeds = [];
        $this->scanModule($module, $path, str_ends_with($relative, StoryTokenParser::FILE_SUFFIX), 'extends');
    }

    private function scanModule(ModuleNode $module, string $path, bool $story, string $kind): void
    {
        if ($module->hasNode('parent')) {
            $this->reference($module->getNode('parent'), $kind, $path, $story);
        }
        $this->scanNode($module, $path, $story);

        // An embed is a template of its own, whose parent is the embedded name.
        foreach ($module->getAttribute('embedded_templates') as $embedded) {
            /** @var ModuleNode $embedded */
            if (!isset($this->ignoredEmbeds[$embedded->getAttribute('index')])) {
                $this->scanModule($embedded, $path, $story, 'embed');
            }
        }
    }

    private function scanNode(Node $node, string $path, bool $story): void
    {
        if ($node instanceof EmbedNode) {
            if ($node->getAttribute('ignore_missing')) {
                $this->ignoredEmbeds[$node->getAttribute('index')] = true;
            }
        } elseif ($node instanceof IncludeNode) {
            if (!$node->getAttribute('ignore_missing')) {
                $this->reference($node->getNode('expr'), 'include', $path, $story);
            }
        } elseif ($node instanceof FunctionExpression && $node->getAttribute('name') === 'include') {
            // include(template, variables, with_context, ignore_missing)
            $arguments = $node->getNode('arguments');
            $ignore = $arguments->hasNode('ignore_missing') ? $arguments->getNode('ignore_missing') : ($arguments->hasNode('3') ? $arguments->getNode('3') : null);
            $first = $arguments->hasNode('template') ? $arguments->getNode('template') : ($arguments->hasNode('0') ? $arguments->getNode('0') : null);
            if ($first !== null && !($ignore instanceof ConstantExpression && $ignore->getAttribute('value'))) {
                $this->reference($first, 'include', $path, $story);
            }
        }

        foreach ($node as $child) {
            $this->scanNode($child, $path, $story);
        }
    }

    private function reference(Node $name, string $kind, string $path, bool $story): void
    {
        $line = $name->getTemplateLine() ?: null;

        if ($name instanceof ConstantExpression) {
            $handle = $name->getAttribute('value');
            if (is_string($handle) && Loader::owns($handle) && !isset($this->handles[$handle])) {
                $this->found[] = [
                    'code' => $story ? 'CL004' : 'CL003',
                    'path' => $path,
                    'line' => $line,
                    'message' => sprintf('%s %s %s, which isn\'t a component on any site.', $story ? 'A story' : 'It', $kind === 'include' ? 'includes' : ($kind === 'embed' ? 'embeds' : 'extends'), $handle),
                ];
            }

            return;
        }

        // Only a name that starts like a handle can be one. A dynamic path such as
        // "_sites/#{currentSite.handle}/head.twig" is Craft's, and not the check's concern.
        $prefix = self::prefix($name);
        if ($prefix !== null && str_starts_with($prefix, '@') && !str_contains($prefix, '/')) {
            $this->found[] = [
                'code' => 'CL008',
                'path' => $path,
                'line' => $line,
                'message' => sprintf('Its %s name starts %s but is built at run time, so the check can\'t tell whether it exists.', $kind, $prefix),
            ];
        }
    }

    /** The literal start of a name joined with `~` or `#{}`, if it has one. */
    private static function prefix(Node $name): ?string
    {
        if ($name instanceof ConstantExpression) {
            $value = $name->getAttribute('value');

            return is_string($value) ? $value : null;
        }

        return $name instanceof ConcatBinary ? self::prefix($name->getNode('left')) : null;
    }

    /**
     * @param Problem $problem
     */
    private function location(array $problem): string
    {
        return $this->display($problem['path']) . ($problem['line'] !== null ? ":{$problem['line']}" : '');
    }

    /** A path relative to the templates folder, else to the project, so no server path is printed. */
    private function display(string $path): string
    {
        foreach (['@templates', '@root'] as $alias) {
            $base = FileHelper::normalizePath(Craft::getAlias($alias)) . '/';
            if (str_starts_with($path, $base)) {
                return substr($path, strlen($base));
            }
        }

        return $path;
    }
}
