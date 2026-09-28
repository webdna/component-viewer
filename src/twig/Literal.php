<?php

namespace webdna\componentlibrary\twig;

use Twig\Error\SyntaxError;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\Binary\AbstractBinary;
use Twig\Node\Expression\Binary\ConcatBinary;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\MethodCallExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\Unary\NegUnary;
use Twig\Node\Expression\Unary\PosUnary;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Node;
use Twig\Source;

/**
 * The literal-only validator (BR-6).
 *
 * Turns a parsed Twig expression into the PHP value it spells, provided it is built only from
 * strings, numbers, booleans, null, sequences and hashes. Anything else (a variable, filter,
 * function call, operator, attribute access or interpolated string) is a SyntaxError at compile
 * time, so the value can be read by parsing the template and never by rendering it.
 */
final class Literal
{
    /**
     * @param string $path Where the expression sits, for the error message (`props.label`)
     * @throws SyntaxError
     */
    public static function toValue(Node $node, string $tag, string $path, ?Source $source): mixed
    {
        if ($node instanceof ConstantExpression) {
            return $node->getAttribute('value');
        }

        // `-1` parses as a negation of the constant 1.
        if ($node instanceof NegUnary || $node instanceof PosUnary) {
            $operand = $node->getNode('node');
            $value = $operand instanceof ConstantExpression ? $operand->getAttribute('value') : null;
            if (is_int($value) || is_float($value)) {
                return $node instanceof NegUnary ? -$value : $value;
            }
        }

        if ($node instanceof ArrayExpression) {
            $value = [];
            foreach ($node->getKeyValuePairs() as $pair) {
                $key = $pair['key'];
                if (!$key instanceof ConstantExpression) {
                    throw self::error($key, $tag, "$path has a computed key", $source);
                }
                $name = $key->getAttribute('value');
                $value[$name] = self::toValue($pair['value'], $tag, $path === '' ? (string)$name : "$path.$name", $source);
            }

            return $value;
        }

        $where = $path === '' ? 'Its argument' : "\"$path\"";
        throw self::error($node, $tag, sprintf('%s must be a literal, not %s', $where, self::describe($node)), $source);
    }

    public static function error(Node $node, string $tag, string $message, ?Source $source): SyntaxError
    {
        return new SyntaxError("The \"$tag\" tag takes literal values only. $message.", $node->getTemplateLine(), $source);
    }

    private static function describe(Node $node): string
    {
        return match (true) {
            $node instanceof ContextVariable, $node instanceof NameExpression => 'a variable',
            $node instanceof FilterExpression => 'a filter',
            $node instanceof FunctionExpression => 'a function call',
            $node instanceof ConcatBinary => 'a concatenation or interpolated string',
            $node instanceof AbstractBinary => 'an operator',
            $node instanceof GetAttrExpression, $node instanceof MethodCallExpression => 'an attribute or method access',
            default => 'an expression',
        };
    }
}
