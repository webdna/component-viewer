<?php

namespace webdna\componentlibrary\legacy;

use Craft;
use craft\web\View;
use InvalidArgumentException;
use JsonException;
use Throwable;
use Twig\Error\Error as TwigError;
use webdna\componentlibrary\models\Component;
use webdna\componentlibrary\models\Prop;
use webdna\componentlibrary\models\Story;

/**
 * Reads a v1 `<name>.config.json` into the same Component a tag declares (BR-15, BR-16), so a
 * legacy library is listed without converting a file.
 *
 * A v1 config is a Twig template that prints JSON (`{{ raw({…}|json_encode) }}`). It's rendered
 * with renderTemplate() in site mode and no variables, and the index calls this only while it
 * builds (BR-33). Nothing is ever read by rendering a string (B3).
 *
 * @phpstan-import-type Problem from Component
 */
final class ConfigJsonAdapter
{
    public const SUFFIX = '.config.json';

    /** v1's notes file, one per folder. */
    public const README = 'readme.md';

    /** BR-16: kept as written in a default, and rendered from its handle at preview time. */
    public const INCLUDE_PATTERN = '/\{include:(@[\w:-]+)\}/';

    /** BR-16: never resolved, so the value stays literal and the check reports it. */
    private const UNRESOLVED_PATTERN = '/\{(?:ref|entry|asset):[^}]*\}/';

    /** v1 variable types, as the v1 viewer treated them, to prop types. Anything else was a text box. */
    private const TYPES = [
        'string' => 'string',
        'text' => 'text',
        'textarea' => 'text',
        'number' => 'number',
        'checkbox' => 'bool',
        'bool' => 'bool',
        'boolean' => 'bool',
        'lightswitch' => 'bool',
        'json' => 'json',
        'select' => 'select',
    ];

    /**
     * The component a config describes. A config that doesn't render or decode gives a component
     * with only the error, so it's still listed (TN-9). This never throws.
     *
     * @param string $root The root it was found in
     * @param string $relative The config's path relative to the root, with `/` separators
     * @param string|null $readme Absolute path of the folder's readme, if there is one
     */
    public static function read(string $root, string $relative, ?string $readme = null): Component
    {
        $path = "$root/$relative";
        $notes = $readme !== null ? trim((string)file_get_contents($readme)) : '';
        $notes = $notes !== '' ? $notes : null;

        try {
            $config = self::decode(self::render($root, $relative));
        } catch (Throwable $e) {
            return new Component(notes: $notes, configPath: $path, errors: [self::failure($path, $e)]);
        }

        $errors = [];
        $handle = self::string($config, 'handle');
        if ($handle !== null) {
            $handle = '@' . ltrim($handle, '@');
            if (!preg_match(Component::HANDLE_PATTERN, $handle)) {
                $errors[] = self::problem('CL005', $path, null, "Its handle $handle isn't one templates can include, so its path gives the handle instead.");
                $handle = null;
            }
        }

        $status = self::string($config, 'status');
        $context = is_array($config['context'] ?? null) ? $config['context'] : [];
        $variables = is_array($config['variables'] ?? null) ? $config['variables'] : [];
        $stories = self::stories($config['variants'] ?? null, $context);

        return new Component(
            name: self::string($config, 'name'),
            handle: $handle,
            status: in_array($status, Component::STATUSES, true) ? $status : null,
            notes: $notes,
            viewClass: self::string($config, 'viewClass'),
            props: self::props($variables, $context),
            stories: $stories,
            errors: $errors,
            configPath: $path,
            warnings: self::unresolved($path, [$context, ...array_map(fn(Story $story) => $story->props, $stories)]),
        );
    }

    /**
     * Renders the config in site mode, whatever mode the request is in (B3).
     *
     * A config under the site templates folder renders by its name there, so the site-root paths
     * it includes resolve as they did in v1. Craft refuses names outside that folder, so a root
     * elsewhere is made the templates path for this one render.
     */
    private static function render(string $root, string $relative): string
    {
        $view = Craft::$app->getView();
        $mode = $view->getTemplateMode();
        $templatesPath = $view->getTemplatesPath();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        $site = Craft::$app->getPath()->getSiteTemplatesPath();
        $path = "$root/$relative";
        [$base, $name] = str_starts_with($path, "$site/") ? [$site, substr($path, strlen($site) + 1)] : [$root, $relative];

        try {
            $view->setTemplatesPath($base);

            return $view->renderTemplate($name, [], View::TEMPLATE_MODE_SITE);
        } finally {
            $view->setTemplateMode($mode);
            $view->setTemplatesPath($templatesPath);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function decode(string $output): array
    {
        try {
            $config = json_decode(trim($output), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException("It didn't render valid JSON ({$e->getMessage()}).", 0, $e);
        }

        if (!is_array($config) || ($config !== [] && array_is_list($config))) {
            throw new InvalidArgumentException("It didn't render a JSON object.");
        }

        return $config;
    }

    /**
     * BR-15's `variables` as props, with their defaults from the base `context`. Legacy configs
     * were never validated, so a declaration v1 accepted is taken as it is, never refused.
     *
     * @param array<int|string,mixed> $variables
     * @param array<int|string,mixed> $context
     * @return array<string,Prop>
     */
    private static function props(array $variables, array $context): array
    {
        $props = [];
        foreach ($variables as $name => $declaration) {
            $name = (string)$name;
            $declared = is_array($declaration) ? ($declaration['type'] ?? null) : $declaration;
            $type = is_string($declared) ? (self::TYPES[strtolower($declared)] ?? 'string') : 'string';

            $options = $type === 'select' && is_array($declaration) ? self::options($declaration['options'] ?? null) : [];
            if ($type === 'select' && $options === []) {
                $type = 'string';
            }

            $props[$name] = new Prop($name, $type, $context[$name] ?? null, $options);
        }

        return $props;
    }

    /**
     * v1's `[{value, label}]` list, or a plain list or hash.
     *
     * @return array<int|string,string>
     */
    private static function options(mixed $options): array
    {
        if (!is_array($options)) {
            return [];
        }

        $normalised = [];
        foreach ($options as $key => $option) {
            [$value, $label] = match (true) {
                is_array($option) => [$option['value'] ?? null, $option['label'] ?? $option['value'] ?? null],
                array_is_list($options) => [$option, $option],
                default => [$key, $option],
            };
            if ((is_string($value) || is_int($value)) && is_scalar($label)) {
                $normalised[$value] = (string)$label;
            }
        }

        return $normalised;
    }

    /**
     * BR-10: each variant is a story, named by its `name`, with its `context` over the base
     * `context`. Without variants, the base `context` is the *Default* story.
     *
     * @param array<int|string,mixed> $context
     * @return array<string,Story>
     */
    private static function stories(mixed $variants, array $context): array
    {
        if (!is_array($variants) || $variants === []) {
            return [Story::DEFAULT_NAME => new Story(Story::DEFAULT_NAME, $context)];
        }

        $stories = [];
        foreach (array_values($variants) as $i => $variant) {
            $variant = is_array($variant) ? $variant : [];
            $name = trim(self::string($variant, 'name') ?? '');
            $name = $name !== '' ? $name : 'Variant ' . ($i + 1);

            // Story names are unique (BR-8), and v1 never checked.
            $unique = $name;
            for ($n = 2; isset($stories[$unique]); $n++) {
                $unique = "$name ($n)";
            }

            $own = is_array($variant['context'] ?? null) ? $variant['context'] : [];
            $stories[$unique] = new Story($unique, array_merge($context, $own));
        }

        return $stories;
    }

    /**
     * One CL006 warning per distinct `{ref:}`, `{entry:}` or `{asset:}` placeholder (BR-16), at
     * the line it's written on when it's written literally.
     *
     * @param array<int,array<int|string,mixed>> $values
     * @return list<Problem>
     */
    private static function unresolved(string $path, array $values): array
    {
        $found = [];
        array_walk_recursive($values, function(mixed $value) use (&$found) {
            if (is_string($value) && preg_match_all(self::UNRESOLVED_PATTERN, $value, $matches)) {
                $found += array_fill_keys($matches[0], true);
            }
        });

        $source = $found !== [] ? (string)file_get_contents($path) : '';
        $warnings = [];
        foreach (array_keys($found) as $placeholder) {
            $offset = strpos($source, $placeholder);
            $warnings[] = self::problem(
                'CL006',
                $path,
                $offset !== false ? substr_count($source, "\n", 0, $offset) + 1 : null,
                "$placeholder isn't resolved, so the value stays as written. Give it a literal default.",
            );
        }

        return $warnings;
    }

    /**
     * @return Problem
     */
    private static function failure(string $path, Throwable $e): array
    {
        if ($e instanceof TwigError) {
            $line = $e->getTemplateLine();

            return self::problem('CL005', $e->getSourceContext()?->getPath() ?: $path, $line > 0 ? $line : null, "It couldn't be rendered: {$e->getRawMessage()}");
        }

        return self::problem('CL005', $path, null, $e->getMessage());
    }

    /**
     * @return Problem
     */
    private static function problem(string $code, string $path, ?int $line, string $message): array
    {
        return ['code' => $code, 'path' => $path, 'line' => $line, 'message' => $message];
    }

    /**
     * @param array<int|string,mixed> $values
     */
    private static function string(array $values, string $key): ?string
    {
        return is_string($values[$key] ?? null) ? $values[$key] : null;
    }
}
