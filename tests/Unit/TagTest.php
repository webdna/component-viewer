<?php

declare(strict_types=1);

/*
 * The `component` tag (BR-5, BR-6, BR-7) and TN-7.
 *
 * Templates are parsed with Craft's own Twig environments, so these also prove the tag is
 * registered in both template modes. Inline sources are test inputs only: the plugin itself
 * never renders a string (B3).
 */

use craft\web\View;
use Twig\Compiler;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use webdna\componentlibrary\models\Component;
use webdna\componentlibrary\twig\ComponentNode;
use webdna\componentlibrary\twig\Extension;

/** The same source with its component tag, and the newline Twig trims after it, cut out. */
function withoutTag(string $source): string
{
    return preg_replace('/\{%-?\s*component\b.*?%\}\n?/s', '', $source, 1);
}

it('reads the fixture tag in both template modes', function(string $mode) {
    $component = parseTag(file_get_contents(FIXTURES . '/ui/good.twig'), 'ui/good.twig', $mode);

    expect($component)->toBeInstanceOf(Component::class)
        ->and($component->name)->toBe('Good button')
        ->and($component->status)->toBe('ready')
        ->and($component->notes)->toContain('**fixture**')
        ->and($component->viewClass)->toBe('p-4')
        ->and(array_keys($component->props))
        ->toBe(['label', 'count', 'offset', 'disabled', 'attrs', 'style', 'size', 'body', 'icon']);
})->with([View::TEMPLATE_MODE_SITE, View::TEMPLATE_MODE_CP]);

it('infers shorthand prop types and keeps full declarations', function() {
    $props = parseTag(file_get_contents(FIXTURES . '/ui/good.twig'))->props;

    $summary = array_map(fn($p) => [$p->type, $p->default], $props);
    expect($summary)->toBe([
        'label' => ['string', 'Save'],
        'count' => ['number', 3],
        'offset' => ['number', -1.5],
        'disabled' => ['bool', false],
        'attrs' => ['json', ['data-x' => 1]],
        'style' => ['select', 'primary'],
        'size' => ['select', null],
        'body' => ['text', null],
        'icon' => ['string', null],
    ])
        ->and($props['style']->options)->toBe(['primary' => 'primary', 'secondary' => 'secondary'])
        ->and($props['size']->options)->toBe(['sm' => 'Small', 'lg' => 'Large'])
        ->and($props['size']->required)->toBeTrue()
        ->and($props['body']->description)->toBe('Shown under the label');
});

it('normalises the handle to a leading @', function() {
    expect(parseTag("{% component { handle: 'ui:button' } %}")->handle)->toBe('@ui:button')
        ->and(parseTag("{% component { handle: '@ui:button--large' } %}")->handle)->toBe('@ui:button--large');
});

it('accepts an empty hash and a tag anywhere in the file', function() {
    expect(parseTag('{% component {} %}'))->toBeInstanceOf(Component::class)
        ->and(parseTag("<div>{% if x %}{% block b %}{% component { name: 'Deep' } %}{% endblock %}{% endif %}</div>")->name)
        ->toBe('Deep');
});

// TN-7
it('refuses the bad-tag fixture at compile time, naming the file and line', function() {
    $path = FIXTURES . '/ui/bad-tag.twig';
    $twig = twigIn(View::TEMPLATE_MODE_SITE);

    try {
        $twig->parse($twig->tokenize(new Source(file_get_contents($path), 'ui/bad-tag.twig', $path)));
        $this->fail('bad-tag.twig compiled');
    } catch (SyntaxError $e) {
        expect($e->getMessage())->toContain('"name" must be a literal, not a variable')
            ->toContain('"ui/bad-tag.twig" at line 3')
            ->and($e->getTemplateLine())->toBe(3)
            ->and($e->getSourceContext()->getName())->toBe('ui/bad-tag.twig');
    }
});

it('refuses anything that is not a literal', function(string $argument, string $message) {
    expect(fn() => parseTag("{% component $argument %}"))->toThrow(SyntaxError::class, $message);
})->with([
    'variable' => ['{ name: foo }', '"name" must be a literal, not a variable'],
    'shorthand key' => ['{ name }', '"name" must be a literal, not a variable'],
    'filter' => ["{ name: 'a'|upper }", 'not a filter'],
    'function' => ['{ name: random() }', 'not a function call'],
    'concatenation' => ["{ name: 'a' ~ 'b' }", 'not a concatenation'],
    'interpolation' => ['{ name: "a#{b}" }', 'not a concatenation'],
    'operator' => ['{ props: { n: 1 + 1 } }', '"props.n" must be a literal, not an operator'],
    'attribute' => ['{ name: foo.bar }', 'not an attribute or method access'],
    'negated variable' => ['{ props: { n: -foo } }', '"props.n" must be a literal'],
    'computed key' => ['{ (k): 1 }', 'has a computed key'],
    'deep' => ["{ props: { x: { type: 'json', default: [1, foo] } } }", '"props.x.default.1" must be a literal'],
    'filtered hash' => ['{ name: 1 }|merge({})', 'Its argument must be a literal, not a filter'],
    'variable argument' => ['defaults', 'Its argument must be a literal, not a variable'],
]);

it('refuses a tag that does not describe a component', function(string $source, string $message) {
    expect(fn() => parseTag($source))->toThrow(SyntaxError::class, $message);
})->with([
    'no argument' => ['{% component %}', 'needs a hash'],
    'string argument' => ["{% component 'Button' %}", 'Its argument must be a hash'],
    'list argument' => ["{% component ['Button'] %}", 'must be a hash, not a list'],
    'unknown key' => ["{% component { title: 'x' } %}", 'Unknown key "title"'],
    'bad status' => ["{% component { status: 'done' } %}", '"status" must be one of'],
    'bad background' => ["{% component { background: 'purple' } %}", '"background" must be one of site, light, dark'],
    'background case' => ["{% component { background: 'Dark' } %}", '"background" must be one of'],
    'non-string background' => ['{% component { background: true } %}', '"background" must be a string'],
    'bad handle' => ["{% component { handle: 'button' } %}", '"handle" must look like'],
    'non-string name' => ['{% component { name: 3 } %}', '"name" must be a string'],
    'props list' => ["{% component { props: ['a'] } %}", '"props" must be a hash'],
    'prop name' => ["{% component { props: { 'my-label': 'x' } } %}", 'not a valid prop name'],
    'unknown type' => ["{% component { props: { a: { type: 'date' } } } %}", '"props.a.type" must be one of'],
    'select without options' => ["{% component { props: { a: { type: 'select' } } } %}", 'needs a non-empty "options"'],
    'options on string' => ["{% component { props: { a: { type: 'string', options: ['x'] } } } %}", 'applies to the select type only'],
    'default not an option' => ["{% component { props: { a: { type: 'select', options: ['x'], default: 'y' } } } %}", 'must be one of its options'],
    'default of wrong type' => ["{% component { props: { a: { type: 'number', default: 'many' } } } %}", 'does not match type number'],
    'required not bool' => ["{% component { props: { a: { type: 'bool', required: 'yes' } } } %}", '"props.a.required" must be true or false'],
    'two tags' => ['{% component {} %}{% component {} %}', 'only one component tag'],
]);

it('reports a nested literal error on its own line', function() {
    $source = "{% component {\n    name: 'x',\n    props: {\n        a: foo,\n    },\n} %}";

    try {
        parseTag($source);
        $this->fail('compiled');
    } catch (SyntaxError $e) {
        expect($e->getTemplateLine())->toBe(4);
    }
});

it('treats a hash without a type as a json default', function() {
    $prop = parseTag("{% component { props: { a: { default: 'x', other: 1 } } } %}")->props['a'];

    expect($prop->type)->toBe('json')->and($prop->default)->toBe(['default' => 'x', 'other' => 1]);
});

// BR-7
it('compiles to nothing', function() {
    $twig = twigIn(View::TEMPLATE_MODE_SITE);
    $node = ComponentNode::find($twig->parse($twig->tokenize(new Source('{% component {} %}', 'x.twig'))));

    expect((new Compiler($twig))->compile($node)->getSource())->toBe('');
});

it('renders byte-identical output with and without the tag', function(array $context) {
    $source = file_get_contents(FIXTURES . '/ui/good.twig');
    $twig = twigIn(View::TEMPLATE_MODE_SITE);

    $with = $twig->createTemplate($source)->render($context);
    $without = $twig->createTemplate(withoutTag($source))->render($context);

    expect(withoutTag($source))->not->toContain('component {')
        ->and($with)->toBe($without)
        ->and($with)->toContain('<button');
})->with([
    'defaults' => [[]],
    'props' => [['label' => 'Go', 'style' => 'secondary', 'disabled' => true]],
]);

it('renders byte-identical output inside a block and in a child template', function() {
    $twig = new Environment(new ArrayLoader([
        'base.twig' => "<main>{% block content %}{% endblock %}</main>\n",
        'block.twig' => "a\n{% block content %}\n  {% component { name: 'x' } %}\n  b\n{% endblock %}\nc\n",
        'child.twig' => "{% extends 'base.twig' %}\n{% component { name: 'x' } %}\n{% block content %}b{% endblock %}\n",
    ]));
    $twig->addExtension(new Extension());

    $plain = new Environment(new ArrayLoader([
        'base.twig' => "<main>{% block content %}{% endblock %}</main>\n",
        'block.twig' => withoutTag("a\n{% block content %}\n  {% component { name: 'x' } %}\n  b\n{% endblock %}\nc\n"),
        'child.twig' => withoutTag("{% extends 'base.twig' %}\n{% component { name: 'x' } %}\n{% block content %}b{% endblock %}\n"),
    ]));

    expect($twig->render('block.twig'))->toBe($plain->render('block.twig'))
        ->and($twig->render('child.twig'))->toBe($plain->render('child.twig'))
        ->and($twig->render('child.twig'))->toBe("<main>b</main>\n");
});
