<?php

namespace webdna\componentlibrary\web\assets\viewer;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * The viewer's own script and styles, plain files with no build step. The script is a vanilla ES
 * module and needs nothing from the CP's JS, so the share viewer (task 4.2) can load it too.
 */
class ViewerAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__;
        $this->depends = [CpAsset::class];
        $this->js = ['viewer.js'];
        $this->jsOptions = ['type' => 'module'];
        $this->css = ['viewer.css'];

        parent::init();
    }
}
