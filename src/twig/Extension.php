<?php

namespace webdna\componentlibrary\twig;

use Twig\Extension\AbstractExtension;

/**
 * Registers the library's tags on both the site and CP Twig environments, so a component file
 * compiles wherever Craft renders it.
 */
final class Extension extends AbstractExtension
{
    public function getTokenParsers(): array
    {
        return [
            new ComponentTokenParser(),
            new StoryTokenParser(),
        ];
    }
}
