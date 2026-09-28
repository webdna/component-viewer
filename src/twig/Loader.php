<?php

namespace webdna\componentlibrary\twig;

use Craft;
use craft\web\twig\TemplateLoaderException;
use Twig\Loader\LoaderInterface;
use Twig\Source;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\models\Component;

/**
 * Resolves component handles (`@ui:button`) for one Twig environment, and hands every other name,
 * untouched, to the loader Craft gave that environment (BR-17, BR-18).
 *
 * It wraps, never replaces. v1 swapped the loader of whichever environment existed at init for the
 * whole app. ComponentLibrary wraps each site and CP environment as Craft creates it, so Twig
 * namespaces, plain paths and every CP and plugin template resolve exactly as without the plugin.
 *
 * A handle resolves through the index for Craft's current site, never through the request. The
 * file may sit outside the templates folder (a configured root), which Craft's own loader refuses,
 * so this loader reads it itself. An unknown handle is Craft's usual missing-template error, a
 * Twig LoaderError, which is what `ignore missing` catches.
 */
final class Loader implements LoaderInterface
{
    public function __construct(
        public readonly LoaderInterface $inner,
    ) {
    }

    /**
     * Whether a template name is a component handle, and so this loader's to resolve (BR-17).
     */
    public static function owns(string $name): bool
    {
        return (bool)preg_match(Component::HANDLE_PATTERN, $name);
    }

    public function exists(string $name): bool
    {
        if (!self::owns($name)) {
            return $this->inner->exists($name);
        }

        return ComponentLibrary::getInstance()->getIndex()->get($name)?->path !== null;
    }

    public function getSourceContext(string $name): Source
    {
        if (!self::owns($name)) {
            return $this->inner->getSourceContext($name);
        }

        $path = $this->path($name);
        if (!is_readable($path)) {
            throw new TemplateLoaderException($name, Craft::t('app', 'Tried to read the template at {path}, but could not. Check the permissions.', ['path' => $path]));
        }

        return new Source(file_get_contents($path), $name, $path);
    }

    /**
     * The absolute path, as Craft's own loader gives, so a component compiles once whether it's
     * included by handle or by path, and a site version compiles apart from the file it replaces.
     */
    public function getCacheKey(string $name): string
    {
        return self::owns($name) ? $this->path($name) : $this->inner->getCacheKey($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        if (!self::owns($name)) {
            return $this->inner->isFresh($name, $time);
        }

        $path = $this->path($name);

        return is_file($path) && filemtime($path) <= $time;
    }

    /**
     * @throws TemplateLoaderException if the current site has no component by that handle
     */
    private function path(string $handle): string
    {
        $path = ComponentLibrary::getInstance()->getIndex()->get($handle)?->path;

        if ($path === null) {
            throw new TemplateLoaderException($handle, Craft::t('app', 'Unable to find the template “{template}”.', ['template' => $handle]));
        }

        return $path;
    }
}
