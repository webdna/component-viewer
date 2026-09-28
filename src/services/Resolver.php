<?php

namespace webdna\componentlibrary\services;

use Craft;
use craft\helpers\FileHelper;
use webdna\componentlibrary\ComponentLibrary;
use yii\base\Component as BaseComponent;

/**
 * Finds a file across the library's roots, for code that renders templates of its own from them,
 * such as mw-core's Handlebars service (BR-19). A documented, stable public API.
 *
 * ```php
 * ComponentLibrary::getInstance()->getResolver()->resolve('cards/price.hbs', 'second');
 * ```
 */
class Resolver extends BaseComponent
{
    /** The index whose roots are searched. The plugin's own when unset. */
    public ?Index $index = null;

    /**
     * The absolute path of `$path` in the highest-precedence root holding it (BR-11 order, so a
     * site version beats the file it replaces), or null.
     *
     * @param string $path Relative to a root, with its extension. A path containing `..`, or
     * starting with `/`, returns null.
     * @param string|null $siteHandle The site whose versions are searched, the current site when
     * null. A handle Craft doesn't know returns null.
     */
    public function resolve(string $path, ?string $siteHandle = null): ?string
    {
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, "\0")) {
            return null;
        }

        // Only Craft's own handle becomes a path segment: the argument may have come from a
        // request, which is how v1's `?site=` traversal worked.
        if ($siteHandle !== null) {
            $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
            if ($site === null) {
                return null;
            }
            $siteHandle = $site->handle;
        }

        $index = $this->index ?? ComponentLibrary::getInstance()->getIndex();
        $roots = $index->roots($siteHandle);
        $sites = $index->sitesFolder();
        $siteRoot = $sites !== null ? end($roots) : null;

        foreach (array_reverse($roots) as $root) {
            $file = FileHelper::normalizePath("$root/$path");

            // As in the index, another root never reaches into the sites folder, so no path
            // reaches a different site's version.
            if ($sites !== null && $root !== $siteRoot && str_starts_with($file, "$sites/")) {
                continue;
            }

            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }
}
