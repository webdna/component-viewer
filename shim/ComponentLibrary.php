<?php

namespace webdna\componentlibrary;

use yii\base\InvalidConfigException;
use yii\base\Module;

/**
 * v1's class name. v1 was a module called Component Library, registered in config/app.php as
 * `'component-library' => ComponentLibrary::class`. Without this class a v1 site that updates
 * would stop with a bare "class not found"; with it, the site is told what to do (BR-4).
 */
class ComponentLibrary extends Module
{
    public function init(): void
    {
        throw new InvalidConfigException(
            'Component Library is now the Component Viewer plugin. Remove ' .
            "'component-library' => ComponentLibrary::class from 'modules', 'component-library' from " .
            "'bootstrap' and the `use webdna\\componentlibrary\\ComponentLibrary;` line in config/app.php, " .
            'then run `php craft plugin/install component-viewer`.'
        );
    }
}
