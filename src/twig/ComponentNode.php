<?php

namespace webdna\componentlibrary\twig;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;
use webdna\componentlibrary\models\Component;

/**
 * A parsed `{% component %}` tag. It compiles to nothing (BR-7): the declaration is for the
 * library, which reads it from the parsed tree, and never reaches the live site's output.
 *
 * It implements no output interface and has no child nodes, so Twig treats it as empty and allows
 * it outside blocks in a child template.
 */
#[YieldReady]
final class ComponentNode extends Node
{
    public function __construct(Component $component, int $lineno)
    {
        parent::__construct([], ['component' => $component], $lineno);
    }

    public function getComponent(): Component
    {
        return $this->getAttribute('component');
    }

    public function compile(Compiler $compiler): void
    {
    }

    /**
     * The component tag within a parsed template, wherever it sits (body, block or macro).
     */
    public static function find(Node $node): ?self
    {
        if ($node instanceof self) {
            return $node;
        }

        foreach ($node as $child) {
            if ($found = self::find($child)) {
                return $found;
            }
        }

        return null;
    }
}
