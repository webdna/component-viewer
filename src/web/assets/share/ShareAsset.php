<?php

namespace webdna\componentlibrary\web\assets\share;

use craft\web\AssetBundle;

/**
 * The share viewer's page: the viewer's own script and styles on the CP's stylesheets only
 * (CpStylesAsset), plus the link pages' styles. viewer.js drives the whole workspace, tabs
 * included, so the page needs no script of its own. It lists the viewer's files itself rather
 * than depending on ViewerAsset, which depends on CpAsset.
 */
class ShareAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = dirname(__DIR__);
        $this->publishOptions = ['except' => ['*.php']];
        $this->depends = [CpStylesAsset::class];
        $this->js = ['viewer/viewer.js'];
        $this->jsOptions = ['type' => 'module'];
        $this->css = ['viewer/viewer.css', 'share/share.css'];

        parent::init();
    }
}
