<?php

namespace webdna\componentlibrary\models;

use InvalidArgumentException;

/**
 * A component's self-description, as declared by its `component` tag (BR-5).
 *
 * Every field is optional in the tag. Where the file lives, and the handle derived from its path
 * when the tag gives none, belong to the index (BR-13), which fills the location fields through
 * with(). A component straight from the tag has none of them.
 */
final class Component
{
    public const STATUSES = ['prototype', 'wip', 'ready', 'deprecated'];

    /** BR-17's owned-name pattern. The tag may leave off the leading `@`. */
    public const HANDLE_PATTERN = '/^@[A-Za-z0-9_-]+(:[A-Za-z0-9_-]+)+$/';

    private const KEYS = ['name', 'handle', 'status', 'notes', 'viewClass', 'props'];

    /**
     * @param array<string,Prop> $props Keyed by prop name, in declared order
     * @param string|null $path Absolute path of the component file
     * @param string|null $root The root it was found in (BR-11)
     * @param array<string,Story> $stories Keyed by name, in file order
     * @param string|null $storiesPath Absolute path of its stories file, if it has one
     * @param string|null $overrides The file in an earlier root this one replaces, e.g. as a site version (BR-13)
     * @param list<string> $duplicates Later files in the same root that claimed this handle and lost (BR-13)
     * @param list<array{path:string,line:int|null,message:string}> $errors Why the file or its stories didn't parse
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $handle = null,
        public readonly ?string $status = null,
        public readonly ?string $notes = null,
        public readonly ?string $viewClass = null,
        public readonly array $props = [],
        public readonly ?string $path = null,
        public readonly ?string $root = null,
        public readonly array $stories = [],
        public readonly ?string $storiesPath = null,
        public readonly ?string $overrides = null,
        public readonly array $duplicates = [],
        public readonly array $errors = [],
    ) {
    }

    /**
     * A copy with the given fields changed.
     *
     * @param array<string,mixed> $changes Constructor arguments by name
     */
    public function with(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }

    /**
     * Builds a component from the tag's literal hash.
     *
     * @param array<int|string,mixed> $definition
     * @throws InvalidArgumentException naming the offending key
     */
    public static function fromDefinition(array $definition): self
    {
        if ($definition !== [] && array_is_list($definition)) {
            throw new InvalidArgumentException('Its argument must be a hash, not a list');
        }

        $unknown = array_diff(array_keys($definition), self::KEYS);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('Unknown key "%s". Keys are %s', reset($unknown), implode(', ', self::KEYS)));
        }

        foreach (['name', 'handle', 'status', 'notes', 'viewClass'] as $key) {
            if (isset($definition[$key]) && !is_string($definition[$key])) {
                throw new InvalidArgumentException("\"$key\" must be a string");
            }
        }

        $handle = $definition['handle'] ?? null;
        if ($handle !== null) {
            $handle = '@' . ltrim($handle, '@');
            if (!preg_match(self::HANDLE_PATTERN, $handle)) {
                throw new InvalidArgumentException("\"handle\" must look like @category:name");
            }
        }

        $status = $definition['status'] ?? null;
        if ($status !== null && !in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException(sprintf('"status" must be one of %s', implode(', ', self::STATUSES)));
        }

        $declared = $definition['props'] ?? [];
        if (!is_array($declared) || ($declared !== [] && array_is_list($declared))) {
            throw new InvalidArgumentException('"props" must be a hash of prop name to declaration');
        }

        $props = [];
        foreach ($declared as $propName => $declaration) {
            $props[(string)$propName] = Prop::fromDeclaration((string)$propName, $declaration);
        }

        return new self(
            $definition['name'] ?? null,
            $handle,
            $status,
            $definition['notes'] ?? null,
            $definition['viewClass'] ?? null,
            $props,
        );
    }
}
