<?php

namespace webdna\componentlibrary\twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;
use webdna\componentlibrary\base\Formatters;

class ComponentLibraryTwigExtension extends AbstractExtension implements GlobalsInterface
{
    public function getGlobals(): array
    {
        return [
            'formatter' => new Formatters(),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getComponentLibraryKey', function() {
                return getenv('COMPONENT_LIBRARY_VIEW_KEY');
            }),
        ];
    }
}
