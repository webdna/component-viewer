<?php

namespace webdna\componentlibrary\console\controllers;

use craft\console\Controller;
use craft\helpers\FileHelper;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\services\Index;
use webdna\componentlibrary\twig\StoryTokenParser;
use yii\console\ExitCode;

/**
 * Starts a component (BR-32): a template with a `component` tag and a stories file with one
 * *Default* story, in the first root or the one asked for.
 *
 * ```
 * craft component-library/make <category>/<name> [--root=<n>] [--folder]
 * ```
 *
 * Roots are numbered from 1 in `templateDirectories` order. A site folder isn't one of them: a site
 * version overrides a component that already exists, so it's copied, not made.
 */
class MakeController extends Controller
{
    /** What each segment of `<category>/<name>` may be. */
    public const SEGMENT_PATTERN = '/^[a-z0-9-]+$/D';

    public $defaultAction = 'index';

    /** Which root to write into, from 1. The first if not set. */
    public ?string $root = null;

    /** Nest the two files in a folder of the component's name. */
    public bool $folder = false;

    public function options($actionID): array
    {
        return [...parent::options($actionID), 'root', 'folder'];
    }

    /**
     * Writes `<name>.twig` and `<name>.stories.twig`. Refuses, writing nothing, if either exists.
     *
     * @param string $path `<category>/<name>`, each of lowercase letters, digits and hyphens
     */
    public function actionIndex(string $path): int
    {
        $segments = explode('/', $path);
        if (count($segments) !== 2 || preg_grep(self::SEGMENT_PATTERN, $segments, PREG_GREP_INVERT)) {
            $this->stderr("Give the component as <category>/<name>, each of lowercase letters, digits and hyphens, such as ui/badge.\n");

            return ExitCode::USAGE;
        }
        [$category, $name] = $segments;

        $index = ComponentLibrary::getInstance()->getIndex();
        $roots = $this->roots($index);
        $number = $this->root ?? '1';
        if (!ctype_digit($number) || (int)$number < 1 || (int)$number > count($roots)) {
            $this->stderr(sprintf("There's no root %s. templateDirectories has %d, numbered from 1.\n", $number, count($roots)));

            return ExitCode::USAGE;
        }
        $root = $roots[(int)$number - 1];

        $relative = $this->folder ? "$category/$name/$name" : "$category/$name";
        $files = [
            "$root/$relative.twig" => $this->component($name),
            "$root/$relative" . StoryTokenParser::FILE_SUFFIX => "{% story 'Default' %}{% endstory %}\n",
        ];

        foreach (array_keys($files) as $file) {
            if (file_exists($file)) {
                $this->stderr(sprintf("%s already exists, so nothing was written.\n", CheckController::display($file)));

                return ExitCode::CANTCREAT;
            }
        }

        // The other layout (ui/badge.twig beside ui/badge/badge.twig) would claim the same handle
        // in the same root, which the check reports as a duplicate (CL002).
        $handle = Index::handleFor("$relative.twig");
        $existing = $index->get($handle);
        if ($existing !== null && $existing->root === $root) {
            $this->stderr(sprintf("%s is already %s, so nothing was written.\n", $handle, CheckController::display((string)$existing->path)));

            return ExitCode::CANTCREAT;
        }

        foreach ($files as $file => $contents) {
            FileHelper::writeToFile($file, $contents);
            $this->stdout('Created ' . CheckController::display($file) . "\n");
        }

        // Seen on the next page load, even where the cached index isn't checked against the disk.
        $index->invalidate();

        return ExitCode::OK;
    }

    /**
     * The configured roots, without the current site's folder.
     *
     * @return list<string>
     */
    private function roots(Index $index): array
    {
        $sites = $index->sitesFolder();

        return array_values(array_filter($index->roots(), fn(string $root) => $sites === null || !str_starts_with($root, "$sites/")));
    }

    private function component(string $name): string
    {
        $title = ucfirst(str_replace('-', ' ', $name));

        return <<<TWIG
{% component {
    name: '$title',
    status: 'wip',
} %}
<div class="$name"></div>

TWIG;
    }
}
