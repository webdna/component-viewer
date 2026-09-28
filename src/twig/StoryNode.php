<?php

namespace webdna\componentlibrary\twig;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use webdna\componentlibrary\models\Story;

/**
 * A parsed `{% story %}` tag. Like the component tag it compiles to nothing: the story's body,
 * if any, lives in a block of its own that only the preview renders (BR-9).
 */
#[YieldReady]
final class StoryNode extends Node
{
    public function __construct(Story $story, int $lineno)
    {
        parent::__construct([], ['story' => $story], $lineno);
    }

    public function getStory(): Story
    {
        return $this->getAttribute('story');
    }

    public function compile(Compiler $compiler): void
    {
    }

    /**
     * The stories declared by a parsed stories file, keyed by name, in file order.
     *
     * @return array<string,Story>
     */
    public static function findAll(ModuleNode $module): array
    {
        $stories = [];
        foreach (self::walk($module->getNode('body')) as $node) {
            $story = $node->getStory();
            $stories[$story->name] = $story;
        }

        return $stories;
    }

    /**
     * @return iterable<self>
     */
    private static function walk(Node $node): iterable
    {
        if ($node instanceof self) {
            yield $node;

            return;
        }

        foreach ($node as $child) {
            yield from self::walk($child);
        }
    }
}
