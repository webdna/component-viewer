<?php

namespace webdna\componentlibrary\helpers;

use cebe\markdown\Markdown;
use Craft;
use craft\helpers\Json;
use craft\helpers\StringHelper;
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
    public static function getComponentContext($componentId, $variant, $parent=null): array
    {
        $componentMap = ComponentLibrary::getInstance()->formatters->getComponentMap();
    
        // load in the components-map.json file as an array
        $componentConfigPath = $componentMap[$componentId];
        // swap out the '.twig' extension for '.config.json'
        //$componentConfig = Json::decode(file_get_contents(self::getComponentConfigPath($componentConfigPath)));
        $componentConfig = self::getComponentConfig($componentConfigPath);
    
        $context = $componentConfig['context'] ?? [];
        $variants = $componentConfig['variants'] ?? [];
    
        foreach ($context as $key => $value) {
            $context[$key] = Craft::$app->getRequest()->getParam($key, $value);                   
        }
        
        $context = self::normalizeValues($context);
        
        foreach ($variants as $key => $var) {
            $varContext = $var['context'] ?? [];
            
            foreach ($varContext as $k => $value) {
                $varContext[$k] = Craft::$app->getRequest()->getParam($k, $value);                   
            }
            $var['context'] = self::normalizeValues($varContext);
            $variants[$key] = $var;
        }
        
        $variant -= 1;
        
        // replace any nested includes
        if (!$parent || $parent == $componentId) {
            foreach ($context as $key => $value) {
                if (is_string($value)) {
                    preg_match_all('/(@\w+:[\w-]+)/', $value, $matches);
                    foreach ($matches[0] as $match) {
                        $tcomponentConfigPath = $componentMap[$match];
                        $tcontext = Json::encode(self::getComponentContext($match, null, $componentId));
                        $t = Craft::$app->view->renderString("{{ include('$match', $tcontext) }}");
                        $context = str_replace($match, $t, $context);
                    }
                }
            }
        }
    
        if (isset($variants[(int)$variant])) {
            // merge context with variant context, variant should always override if matching props
            return array_merge($context, $variants[(int)$variant]['context']);
        }
        
    
        if ($context) {
            return $context;
        }
    
        return $variants[0]['context'] ?? [];
    }
    
    public static function getComponentId($componentId, $variant=0): string
    {
        if ($variant == 0) {
            return $componentId;
        }
        
        $componentMap = ComponentLibrary::getInstance()->formatters->getComponentMap();
        $componentConfigPath = $componentMap[$componentId];
        $componentConfig = self::getComponentConfig($componentConfigPath);
        
        $variantHandle = $componentConfig['variants'][$variant-1]['handle'];
        
        return '@'.$variantHandle;
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
                if (!str_contains($componentId, '--')) {
                    $config = self::getComponentConfig($componentPath);
                    $componentParts = explode(':', $componentId);
    
                    // remove any '@' value from $exploded and capitalize the first letter of the string
                    [$componentGroup, $componentName] = $componentParts;
                    $componentGroup = ucfirst(str_replace('@', '', $componentGroup));
                    $componentName = $componentName ?? '';
    
                    $componentConfig[$componentGroup][$componentName] = $config;
                }
            } catch (\Exception $e) {
                /*$errors[$componentId] = [
                    'error' => [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'line' => $e->getLine(),
                    ],
                    'path' => self::getComponentConfigPath($componentPath),
                ];*/
            }
        }

        // Adds a 'buttons' array to each component that normalizes variant and non-variant data into
        // a single array for our sidebar behavior
        foreach ($componentConfig as $componentGroup => $componentGroupConfig) {
            foreach ($componentGroupConfig as $componentName => $config) {

                $buttons = [];
                if (!empty($config['variants'])) {
                    foreach ($config['variants'] as $variant) {
                        $buttons[] = [
                            'label' => $variant['name'],
                            'status' => $variant['status'] ?? 'prototype',
                        ];
                    }
                } else {
                    $buttons[] = [
                        'label' => $config['name'] ?? $componentName,
                        'status' => $config['status'] ?? 'prototype',
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
        $componentConfig = self::getComponentConfig($componentConfigPath);
        $twigString = file_get_contents($componentMap[self::getComponentId($componentId, $variant)]);
        
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
            $config = $componentConfig;
            if ($variant) {
                $config = array_merge($config, $componentConfig['variants'][$variant-1]);
            }
            $info = Craft::$app->view->renderString($info, ['config' => $config]);
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

    /**
     * @throws Exception
     */
    public static function normalizeValues(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = self::normalizeValues($value);
            } else {
                $normalized[$key] = self::normalizeValue($value);
            }
        }

        return $normalized;
    }

    /**
     * @param string $value
     * @return bool|string|ElementInterface|null
     */
    public static function normalizeValue(string $value): mixed
    {
        if (str_contains($value, '{ref:') &&  Craft::$app->getElements()->parseRefs($value)) {
            $elementService = Craft::$app->getElements();
            $core = StringHelper::trim($value, '{}');
            $parts = array_pad(explode(':', $core), 3, null);
            $refHandle = $parts[1];
            $ref = $parts[2];
            $elementType = $elementService->getElementTypeByRefHandle($refHandle);
            
            $elementQuery = $elementService->createElementQuery($elementType)
            ->status(null);
            
            if ($ref) {
                $elementQuery->id($ref);
            }
            return $elementQuery->one();
        }
        
        $elementService = Craft::$app->getElements();
        preg_match_all('/{(asset|entry):(\w+)(?::)?(\w+)?}/', $value, $matches);
        if ($matches[0]) {
            $elementType = $elementService->getElementTypeByRefHandle($matches[1][0]);
            $elementQuery = $elementService->createElementQuery($elementType)
            ->status(null)->id($matches[2][0]);
            
            return $elementQuery->one();
        }
        

        if (Json::isJsonObject($value)) {

            return Json::decodeIfJson($value);
        }

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if ($value === 'null') {
            return null;
        }

        return $value;
    }
}
