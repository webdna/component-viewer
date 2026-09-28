<?php

namespace webdna\componentlibrary\twig;

use Twig\Error\SyntaxError;
use Twig\Node\BlockNode;
use Twig\Node\EmptyNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Node\TextNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TokenStream;
use WeakMap;
use webdna\componentlibrary\models\Story;

/**
 * Parses `{% story 'Name' with { … } %}…{% endstory %}` in a `.stories.twig` file (BR-8, BR-9).
 *
 * The name and the `with` hash are literals (BR-6). A body that isn't blank becomes a block of
 * the stories file named by {@see blockName()}, so the preview can render one story's body and
 * nothing else. The tag itself compiles to nothing.
 */
final class StoryTokenParser extends AbstractTokenParser
{
    public const TAG = 'story';

    public const FILE_SUFFIX = '.stories.twig';

    /**
     * Per token stream (one per template parse): the story names seen so far, and whether a
     * story body is being parsed right now.
     *
     * @var WeakMap<TokenStream,array{names:array<string,true>,open:bool}>
     */
    private WeakMap $state;

    public function __construct()
    {
        $this->state = new WeakMap();
    }

    public static function blockName(int $index): string
    {
        return "cl_story_$index";
    }

    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();
        $source = $stream->getSourceContext();
        $line = $token->getLine();
        $state = $this->state[$stream] ?? ['names' => [], 'open' => false];

        if (!str_ends_with($source->getName(), self::FILE_SUFFIX)) {
            throw new SyntaxError('The "story" tag belongs in a component\'s .stories.twig file.', $line, $source);
        }
        if ($state['open']) {
            throw new SyntaxError('A story cannot contain another story.', $line, $source);
        }
        if ($this->parser->peekBlockStack() !== null || !$this->parser->isMainScope()) {
            throw new SyntaxError('A story must sit at the top level of its file, not inside a block or macro.', $line, $source);
        }
        if ($stream->test(Token::BLOCK_END_TYPE) || $stream->test(Token::NAME_TYPE, 'with')) {
            throw new SyntaxError('The "story" tag needs a name, as in {% story \'Primary\' %}.', $line, $source);
        }

        $nameExpression = $this->parser->parseExpression();
        $name = Literal::toValue($nameExpression, self::TAG, '', $source);
        if (!is_string($name) || trim($name) === '') {
            throw Literal::error($nameExpression, self::TAG, 'Its name must be a non-empty string', $source);
        }
        if (isset($state['names'][$name])) {
            throw new SyntaxError("Story \"$name\" is already defined in this file.", $line, $source);
        }

        $props = [];
        if ($stream->nextIf(Token::NAME_TYPE, 'with')) {
            $propsExpression = $this->parser->parseExpression();
            $props = Literal::toValue($propsExpression, self::TAG, '', $source);
            if (!is_array($props) || ($props !== [] && array_is_list($props))) {
                throw Literal::error($propsExpression, self::TAG, 'Its "with" must be a hash of props', $source);
            }
        }
        $stream->expect(Token::BLOCK_END_TYPE);

        $state['names'][$name] = true;
        $blockName = self::blockName(count($state['names']));
        $this->state[$stream] = ['open' => true] + $state;

        $this->parser->pushLocalScope();
        $this->parser->pushBlockStack($blockName);
        $body = $this->parser->subparse(fn(Token $token) => $token->test('endstory'), true);
        $this->parser->popBlockStack();
        $this->parser->popLocalScope();
        if ($stream->isEOF()) {
            throw new SyntaxError("Story \"$name\" is missing its {% endstory %}.", $line, $source);
        }
        $stream->expect(Token::BLOCK_END_TYPE);

        $this->state[$stream] = ['open' => false] + $state;

        if (self::isBlank($body)) {
            $blockName = null;
        } else {
            $this->parser->setBlock($blockName, new BlockNode($blockName, $body, $line));
        }

        /** @var array<string,mixed> $props */
        return new StoryNode(new Story($name, $props, $blockName, $line), $line);
    }

    public function getTag(): string
    {
        return self::TAG;
    }

    /**
     * Whether a body renders nothing: whitespace and comments only.
     */
    private static function isBlank(Node $node): bool
    {
        if ($node instanceof TextNode) {
            return $node->isBlank();
        }
        if (!$node instanceof Nodes && !$node instanceof EmptyNode) {
            return false;
        }
        foreach ($node as $child) {
            if (!self::isBlank($child)) {
                return false;
            }
        }

        return true;
    }
}
