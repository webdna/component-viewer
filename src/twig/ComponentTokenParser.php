<?php

namespace webdna\componentlibrary\twig;

use InvalidArgumentException;
use Twig\Error\SyntaxError;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TokenStream;
use WeakMap;
use webdna\componentlibrary\models\Component;

/**
 * Parses `{% component { … } %}` (BR-5, BR-6).
 *
 * The argument must be a hash of literals. Anything else, or a hash that doesn't describe a
 * component, is a SyntaxError naming the file and line, raised when the file compiles.
 */
final class ComponentTokenParser extends AbstractTokenParser
{
    public const TAG = 'component';

    /**
     * The token streams (one per template parse) that already had a component tag.
     *
     * @var WeakMap<TokenStream,true>
     */
    private WeakMap $seen;

    public function __construct()
    {
        $this->seen = new WeakMap();
    }

    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();
        $source = $stream->getSourceContext();
        $line = $token->getLine();

        if ($stream->test(Token::BLOCK_END_TYPE)) {
            throw new SyntaxError('The "component" tag needs a hash, as in {% component { name: \'Button\' } %}.', $line, $source);
        }

        if (isset($this->seen[$stream])) {
            throw new SyntaxError('A file may declare only one component tag.', $line, $source);
        }
        $this->seen[$stream] = true;

        $expression = $this->parser->parseExpression();
        $definition = Literal::toValue($expression, self::TAG, '', $source);
        $stream->expect(Token::BLOCK_END_TYPE);

        if (!is_array($definition)) {
            throw Literal::error($expression, self::TAG, 'Its argument must be a hash', $source);
        }

        try {
            $component = Component::fromDefinition($definition);
        } catch (InvalidArgumentException $e) {
            throw new SyntaxError("Invalid \"component\" tag. {$e->getMessage()}.", $line, $source);
        }

        return new ComponentNode($component, $line);
    }

    public function getTag(): string
    {
        return self::TAG;
    }
}
