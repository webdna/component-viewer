<?php

namespace webdna\componentlibrary\web\assets\share;

use craft\web\AssetBundle;

/**
 * Craft's CP stylesheets without its scripts, for the share viewer on the front end (Appendix A
 * row 2). CpAsset would load there too, but it brings the CP's jQuery and Garnish stack and writes
 * `window.Craft` into the page, which holds the visitor's email and user id when they're logged
 * in on that site. The share viewer needs only the look.
 *
 * Craft's own files, published from its own folders, so they follow Craft updates. The CP theme
 * is named directly because ThemeAsset picks the front-end theme on a site request.
 */
class CpStylesAsset extends AssetBundle
{
    /** Craft's asset folder → the stylesheet in it, in cascade order. */
    public const FILES = [
        '@craft/web/assets/tailwindreset/dist' => 'css/tailwind_reset.css',
        '@craft/web/assets/theme/dist' => 'cp.css',
        '@craft/web/assets/cp/dist' => 'css/cp.css',
    ];

    public function registerAssetFiles($view): void
    {
        $manager = $view->getAssetManager();

        foreach (self::FILES as $folder => $file) {
            [, $url] = $manager->publish($folder);
            $view->registerCssFile("$url/$file");
        }
    }
}
