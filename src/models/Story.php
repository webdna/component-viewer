<?php

namespace webdna\componentlibrary\models;

use Twig\Environment;

/**
 * A named example of a component (BR-8, BR-10): the props it sets and, when it has a body, the
 * block in its stories file that renders it.
 */
final class Story
{
    public const DEFAULT_NAME = 'Default';

    /**
     * @param array<string,mixed> $props The story's `with` hash
     * @param string|null $block The stories-file block holding its body, or null to render the component itself
     */
    public function __construct(
        public readonly string $name,
        public readonly array $props = [],
        public readonly ?string $block = null,
        public readonly ?int $line = null,
    ) {
    }

    /**
     * The one story a component without a stories file has (BR-10).
     */
    public static function fromDefaults(Component $component): self
    {
        return new self(self::DEFAULT_NAME, array_map(fn(Prop $prop) => $prop->default, $component->props));
    }

    /**
     * Renders this story, and only this story (BR-9).
     *
     * A story with a body renders its block of the stories file, with the props as `props`. Any
     * other story renders the component with the props as its context.
     *
     * @param array<string,mixed> $props The current props: the caller merges defaults, the story's
     * own props and any request values, and coerces them, before calling this
     */
    public function render(Environment $twig, string $componentTemplate, ?string $storiesTemplate, array $props): string
    {
        if ($this->block === null || $storiesTemplate === null) {
            return $twig->load($componentTemplate)->render($props);
        }

        return $twig->load($storiesTemplate)->renderBlock($this->block, ['props' => $props]);
    }
}
