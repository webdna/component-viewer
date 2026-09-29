<?php

namespace webdna\componentlibrary\models;

use InvalidArgumentException;

/**
 * One setting a component accepts (BR-5).
 *
 * Declared either as a shorthand default (`label: 'Save'`, type inferred from the value) or in
 * full as `{type, default, options, required, description, control}`. A prop with `control: false`
 * is code-only: documented, set by stories, never by the request. An `icon` holds a name from the
 * `icons` setting (BR-44), which only the index knows, so its default is checked there (CL009).
 */
final class Prop
{
    public const TYPES = ['string', 'text', 'bool', 'number', 'select', 'json', 'icon'];

    private const KEYS = ['type', 'default', 'options', 'required', 'description', 'control'];

    /**
     * @param array<int|string,string> $options Option value => label, for `select` only
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly mixed $default = null,
        public readonly array $options = [],
        public readonly bool $required = false,
        public readonly ?string $description = null,
        public readonly bool $control = true,
    ) {
    }

    /**
     * Builds a prop from its declared value, as read from the tag.
     *
     * @throws InvalidArgumentException naming the offending key
     */
    public static function fromDeclaration(string $name, mixed $declaration): self
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("\"props.$name\" is not a valid prop name (letters, digits and underscores)");
        }

        if (!self::isFullForm($declaration)) {
            return new self($name, self::inferType($declaration), $declaration);
        }

        /** @var array<string,mixed> $declaration */
        $path = "props.$name";
        $default = $declaration['default'] ?? null;
        $type = $declaration['type'] ?? self::inferType($default);

        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(sprintf('"%s.type" must be one of %s', $path, implode(', ', self::TYPES)));
        }

        $options = [];
        if ($type === 'select') {
            $options = self::options($declaration['options'] ?? null, $path);
        } elseif (array_key_exists('options', $declaration)) {
            throw new InvalidArgumentException("\"$path.options\" applies to the select type only");
        }

        if ($default !== null) {
            self::checkDefault($type, $default, $options, $path);
        }

        $required = $declaration['required'] ?? false;
        if (!is_bool($required)) {
            throw new InvalidArgumentException("\"$path.required\" must be true or false");
        }

        $description = $declaration['description'] ?? null;
        if ($description !== null && !is_string($description)) {
            throw new InvalidArgumentException("\"$path.description\" must be a string");
        }

        $control = $declaration['control'] ?? true;
        if (!is_bool($control)) {
            throw new InvalidArgumentException("\"$path.control\" must be true or false");
        }

        return new self($name, $type, $default, $options, $required, $description, $control);
    }

    /**
     * A hash counts as the full form when it names a type, and has no keys the full form lacks.
     * Any other value, including a hash that doesn't, is a shorthand default of type `json`.
     */
    private static function isFullForm(mixed $declaration): bool
    {
        return is_array($declaration)
            && array_key_exists('type', $declaration)
            && array_diff(array_keys($declaration), self::KEYS) === [];
    }

    private static function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'bool',
            is_int($value), is_float($value) => 'number',
            is_array($value) => 'json',
            default => 'string',
        };
    }

    /**
     * @return array<int|string,string>
     */
    private static function options(mixed $options, string $path): array
    {
        if (!is_array($options) || $options === []) {
            throw new InvalidArgumentException("\"$path\" is a select, so it needs a non-empty \"options\" list or hash");
        }

        $normalised = [];
        foreach ($options as $value => $label) {
            if (array_is_list($options)) {
                $value = $label;
            }
            if (!is_string($value) && !is_int($value) || !is_scalar($label)) {
                throw new InvalidArgumentException("\"$path.options\" must hold strings or numbers");
            }
            $normalised[$value] = (string)$label;
        }

        return $normalised;
    }

    /**
     * @param array<int|string,string> $options
     */
    private static function checkDefault(string $type, mixed $default, array $options, string $path): void
    {
        $ok = match ($type) {
            'string', 'text', 'icon' => is_string($default),
            'bool' => is_bool($default),
            'number' => is_int($default) || is_float($default),
            'select' => (is_string($default) || is_int($default)) && in_array((string)$default, array_map('strval', array_keys($options)), true),
            default => true,
        };

        if (!$ok) {
            throw new InvalidArgumentException($type === 'select'
                ? "\"$path.default\" must be one of its options"
                : "\"$path.default\" does not match type $type");
        }
    }
}
