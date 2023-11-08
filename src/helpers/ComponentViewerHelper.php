<?php

namespace webdna\componentlibrary\helpers;

use cebe\markdown\Markdown;
use Craft;
use craft\helpers\Json;
use yii\base\Exception;

use webdna\componentlibrary\ComponentLibrary;

class ComponentViewerHelper
{
    public static function getComponentConfigPath($componentLocation): string
    {
        return str_replace('.twig', '.config.json', $componentLocation);
    }
    
    public static function getComponentConfig($componentLocation): array
    {
        $path = self::getComponentConfigPath($componentLocation);
        
        $config = Craft::$app->view->renderString(file_get_contents($path));
        
        return Json::decode($config, true);
    }

    public static function getComponentReadmePath($componentId, $componentLocation): string
    {
        $componentName = explode(':', $componentId);
        $componentName = $componentName[count($componentName) - 1];
        $readmeFilePath = str_replace($componentName . '.twig', 'readme.md', $componentLocation);
        // if the readmeFilePath contains readme.md
        if (str_contains($readmeFilePath, 'readme.md')) {
            return $readmeFilePath;
        }
        
        return '';
    }
    
    /**
     * @throws Exception
     */
    public static function getComponentViewClass($componentId): array
    {
        $componentMap = ComponentLibrary::getInstance()->formatters->getComponentMap();

        // load in the components-map.json file as an array
        $componentConfigPath = $componentMap[$componentId];
        // swap out the '.twig' extension for '.config.json'
        //$componentConfig = Json::decode(file_get_contents(self::getComponentConfigPath($componentConfigPath)));
        $componentConfig = self::getComponentConfig($componentConfigPath);

        if (array_key_exists('viewClass', $componentConfig)) {
            if (is_array($componentConfig['viewClass'])) {
                return $componentConfig['viewClass'];
            } else {
                return [$componentConfig['viewClass']];
            }
        }
        return [];
    }

    /**
     * @throws Exception
     */
    public static function getComponentContext($componentId, $variant): array
    {
        $componentMap = ComponentLibrary::getInstance()->formatters->getComponentMap();

        // load in the components-map.json file as an array
        $componentConfigPath = $componentMap[$componentId];
        // swap out the '.twig' extension for '.config.json'
        //$componentConfig = Json::decode(file_get_contents(self::getComponentConfigPath($componentConfigPath)));
        $componentConfig = self::getComponentConfig($componentConfigPath);

        $context = $componentConfig['context'] ?? [];
        $variants = $componentConfig['variants'] ?? [];

        if (isset($variants[(int)$variant])) {
            // merge context with variant context, variant should always override if matching props
            return array_merge($context, $variants[(int)$variant]['context']);
        }

        if ($context) {
            return $context;
        }

        return $variants[0]['context'] ?? [];
    }

    /**
     * @throws Exception
     */
    public static function getAllComponents(): array
    {
        $componentMap = ComponentLibrary::getInstance()->formatters->getComponentMap();
        $componentConfig = [];
        $errors = [];

        foreach ($componentMap as $componentId => $componentPath) {
            try {
                //$config = Json::decode(file_get_contents(self::getComponentConfigPath($componentPath)));
                $config = self::getComponentConfig($componentPath);
                $componentParts = explode(':', $componentId);

                // remove any '@' value from $exploded and capitalize the first letter of the string
                [$componentGroup, $componentName] = $componentParts;
                $componentGroup = ucfirst(str_replace('@', '', $componentGroup));
                $componentName = $componentName ?? '';

                $componentConfig[$componentGroup][$componentName] = $config;
            } catch (\Exception $e) {
                $errors[$componentId] = [
                    'error' => [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'line' => $e->getLine(),
                    ],
                    'path' => self::getComponentConfigPath($componentPath),
                ];
            }
        }
        
        //Craft::dd($componentMap);

        // Adds a 'buttons' array to each component that normalizes variant and non-variant data into
        // a single array for our sidebar behavior
        foreach ($componentConfig as $componentGroup => $componentGroupConfig) {
            foreach ($componentGroupConfig as $componentName => $config) {

                $buttons = [];
                if (!empty($config['variants'])) {
                    foreach ($config['variants'] as $variant) {
                        $buttons[] = [
                            'label' => $variant['name'],
                        ];
                    }
                } else {
                    $buttons[] = [
                        'label' => $componentName,
                    ];
                }

                // Odd naming convention here but having buttonVariants->buttons made it easier on template logic for now
                $componentConfig[$componentGroup][$componentName]['buttonVariants']['buttons'] = [
                    'componentId' => $config['handle'],
                    'buttons' => $buttons,
                ];
            }
        }

        if (count($errors)) {
            $componentConfig['Errors'] = $errors;
        }

        return $componentConfig;
    }

    /**
     * @throws Exception
     */
    public static function getComponent($componentId = '', $variant = null): array
    {
        $componentMap = ComponentLibrary::getInstance()->formatters->getComponentMap();

        // load in the components-map.json file as an array
        $componentConfigPath = $componentMap[$componentId];
        $twigString = file_get_contents($componentConfigPath);
        //$componentConfig = Json::decode(file_get_contents(self::getComponentConfigPath($componentConfigPath)));
        $componentConfig = self::getComponentConfig($componentConfigPath);
        try {
            $rendered = Craft::$app->view->renderString($twigString, self::getComponentContext($componentId, $variant));
        } catch (\Exception $e) {
            $rendered = $e->getMessage();
        }

        try {
            $readme = file_get_contents(self::getComponentReadmePath($componentId, $componentConfigPath));
        } catch (\Exception) {
            $readme = '';
        }
        try {
            $parser = new Markdown();
            $readme = $parser->parse($readme);
            $readme = Craft::$app->view->renderString($readme, $componentConfig);
        } catch (\Exception $e) {
            $readme = $e->getMessage();
        }
        
        try {
            $info = file_get_contents(Craft::getAlias('@webdna/componentlibrary/templates/component-viewer/info.twig'));
            $info = Craft::$app->view->renderString($info, ['config' => $componentConfig]);
        } catch (\Exception $e) {
            $info = $e->getMessage();
        }
        
        // render vars
        try {
            $vars = file_get_contents(Craft::getAlias('@webdna/componentlibrary/templates/component-viewer/variables.twig'));
            $vars = Craft::$app->view->renderString($vars, $componentConfig);
        } catch (\Exception $e) {
            $vars = $e->getMessage();
        }

        return [
            'config' => $componentConfig,
            'context' => self::getComponentContext($componentId, $variant),
            'readme' => $readme,
            'twig' => $twigString,
            'rendered' => $rendered,
            'vars' => $vars,
            'info' => $info,
            'filepath' => $componentConfigPath,
        ];
    }

    public static function getLayout(): ?string
    {
        $pluginConfig = Craft::$app->getConfig()->getConfigFromFile('component-library');
        $layout = $pluginConfig['layout'] ?? Craft::getAlias('@webdna/componentlibrary/templates/component-viewer/render.example.twig');
        
        return $layout;
    }
}
