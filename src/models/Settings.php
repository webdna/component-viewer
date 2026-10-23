<?php

namespace webdna\componentviewer\models;

use craft\base\Model;

/**
 * The plugin's settings: `config/component-viewer.php`, which Craft hands to every plugin whose
 * handle names a config file. There is no settings screen. A setting left null keeps the default of
 * the service it configures.
 */
class Settings extends Model
{
    /**
     * The roots, lowest precedence first (BR-11). Aliases allowed. Default `['@templates/_components']`.
     *
     * @var string[]|null
     */
    public ?array $templateDirectories = null;

    /** The folder holding one sub-folder per site handle, if site versions are used (BR-11). */
    public ?string $sites = null;

    /** Whether v1's `.config.json` files are read (BR-12 b, BR-15). Default off. */
    public ?bool $legacy = null;

    /** The folder of icons that `icon` props pick from, by name (BR-44). Alias allowed. */
    public ?string $icons = null;

    /**
     * The preview layout (BR-25): a site template path, or a map of site handle to path.
     *
     * @var string|array<string,string>|null
     */
    public string|array|null $layout = null;
}
