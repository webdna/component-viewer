<?php

declare(strict_types=1);

/*
 * The `story` tag and stories files (BR-8, BR-9, BR-10).
 *
 * Rendering uses a plain Twig environment over the fixture files, with each component also
 * reachable by its handle, because the loader that resolves handles in Craft is task 2.5. The
 * environment is strict, so a story body that reads anything but `props` fails loudly.
 */

use craft\web\View;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use webdna\componentlibrary\models\Story;
use webdna\componentlibrary\twig\Extension;

/**
 * @param array<string,string> $extra More templates, by name
 */
function storyTwig(array $extra = []): Environment
{
    $templates = [];
    foreach (['good', 'nested'] as $name) {
        $templates["ui/$name.twig"] = $templates["@ui:$name"] = file_get_contents(FIXTURES . "/ui/$name.twig");
        $templates["ui/$name.stories.twig"] = file_get_contents(FIXTURES . "/ui/$name.stories.twig");
    }

    $twig = new Environment(new ArrayLoader($extra + $templates), ['strict_variables' => true]);
    $twig->addExtension(new Extension());

    return $twig;
}

/**
 * @return array<string,Story>
 */
function fixtureStories(string $name, string $mode = View::TEMPLATE_MODE_SITE): array
{
    return parseStories(file_get_contents(FIXTURES . "/ui/$name.stories.twig"), "ui/$name.stories.twig", $mode);
}

it('reads the fixture stories in both template modes', function(string $mode) {
    $stories = fixtureStories('good', $mode);

    expect(array_keys($stories))->toBe(['Default', 'Secondary', 'In a toolbar'])
        ->and($stories['Default']->props)->toBe([])
        ->and($stories['Secondary']->props)->toBe(['label' => 'Cancel', 'style' => 'secondary'])
        ->and($stories['Secondary']->line)->toBe(3)
        ->and($stories['Default']->block)->toBeNull()
        ->and($stories['Secondary']->block)->toBeNull()
        ->and($stories['In a toolbar']->block)->not->toBeNull();
})->with([View::TEMPLATE_MODE_SITE, View::TEMPLATE_MODE_CP]);

// BR-8: no body renders the component with the story's props
it('renders a story without a body as the component with its props', function() {
    $twig = storyTwig();
    $story = fixtureStories('good')['Secondary'];

    expect($story->render($twig, 'ui/good.twig', 'ui/good.stories.twig', $story->props))
        ->toBe($twig->render('ui/good.twig', ['label' => 'Cancel', 'style' => 'secondary']))
        ->toContain('btn--secondary')
        ->toContain('>Cancel</button>');
});

// BR-8: a body renders instead, with the current props as `props`
it('renders a body in place of the component, with the current props as props', function() {
    $story = fixtureStories('good')['In a toolbar'];
    $html = $story->render(storyTwig(), 'ui/good.twig', 'ui/good.stories.twig', ['label' => 'Redo', 'style' => 'secondary']);

    expect($html)->toStartWith('<div class="toolbar"><button class="btn btn--secondary">Redo</button>')
        ->toEndWith("</div>\n");
});

it('gives a body props and nothing else', function() {
    $twig = storyTwig(['x.stories.twig' => "{% story 'Leak' %}{{ label }}{% endstory %}"]);
    $story = parseStories("{% story 'Leak' %}{{ label }}{% endstory %}", 'x.stories.twig')['Leak'];

    expect(fn() => $story->render($twig, 'ui/good.twig', 'x.stories.twig', ['label' => 'Go']))
        ->toThrow(RuntimeError::class, 'Variable "label" does not exist');
});

// The nested fixture: a story embedding another component with a block override (TS-8 step 3's shape)
it('renders the custom panel story with its own panel, open, and a component inside it', function() {
    $story = fixtureStories('nested')['Custom panel'];
    $html = $story->render(storyTwig(), 'ui/nested.twig', 'ui/nested.stories.twig', $story->props);

    expect($html)->toContain('role="dialog" aria-labelledby="invite-title" data-open')
        ->toContain('<div data-test="custom-panel">')
        ->toContain('<h2 id="invite-title">Invite somebody</h2>')
        ->toContain('<button class="btn btn--secondary">Send invite</button>')
        ->and($html)->not->toContain('This cannot be undone')
        ->and($html)->not->toContain('>Confirm</button>')
        ->and($html)->not->toContain('Delete saved search?');
});

it('renders the confirm story as the component preset', function() {
    $story = fixtureStories('nested')['Confirm'];
    $html = $story->render(storyTwig(), 'ui/nested.twig', 'ui/nested.stories.twig', $story->props);

    expect($html)->toContain('data-open')
        ->toContain('<h2 id="dialog-title">Delete saved search?</h2>')
        ->toContain('<p>This cannot be undone.</p>')
        ->and($html)->not->toContain('custom-panel');
});

// BR-9
it('executes only the rendered story', function() {
    $source = "{{ include('missing.twig') }}\n"
        . "{% story 'One' %}one{% endstory %}\n"
        . "{% story 'Two' %}{{ include('missing.twig') }}{% endstory %}\n";
    $twig = storyTwig(['x.stories.twig' => $source]);
    $stories = parseStories($source, 'x.stories.twig');

    expect($stories['One']->render($twig, 'ui/good.twig', 'x.stories.twig', []))->toBe('one')
        ->and(fn() => $stories['Two']->render($twig, 'ui/good.twig', 'x.stories.twig', []))
        ->toThrow(LoaderError::class, 'missing.twig');
});

it('renders nothing from a story when its file is rendered whole', function() {
    expect(trim(storyTwig()->render('ui/nested.stories.twig')))->toBe('');
});

// BR-10
it('builds the Default story from the prop defaults when there is no stories file', function() {
    $component = parseTag(file_get_contents(FIXTURES . '/ui/nested.twig'));
    $story = Story::fromDefaults($component);
    $twig = storyTwig();

    expect($story->name)->toBe('Default')
        ->and($story->block)->toBeNull()
        ->and($story->props)->toBe([
            'id' => 'dialog',
            'open' => false,
            'title' => 'Are you sure?',
            'body' => 'This cannot be undone.',
            'confirmLabel' => 'Confirm',
        ])
        ->and($story->render($twig, 'ui/nested.twig', null, $story->props))
        ->toBe($twig->render('ui/nested.twig', $story->props));
});

it('refuses a story tag that breaks the format', function(string $source, string $message, string $name = 'x.stories.twig') {
    expect(fn() => parseStories($source, $name))->toThrow(SyntaxError::class, $message);
})->with([
    'no name' => ['{% story %}{% endstory %}', 'needs a name'],
    'with but no name' => ['{% story with {} %}{% endstory %}', 'needs a name'],
    'variable name' => ['{% story name %}{% endstory %}', 'Its argument must be a literal, not a variable'],
    'interpolated name' => ['{% story "a#{b}" %}{% endstory %}', 'not a concatenation'],
    'number name' => ['{% story 3 %}{% endstory %}', 'Its name must be a non-empty string'],
    'blank name' => ["{% story ' ' %}{% endstory %}", 'Its name must be a non-empty string'],
    'variable with' => ["{% story 'A' with defaults %}{% endstory %}", 'Its argument must be a literal, not a variable'],
    'variable prop' => ["{% story 'A' with { label: foo } %}{% endstory %}", '"label" must be a literal, not a variable'],
    'filtered with' => ["{% story 'A' with { a: 1 }|merge({}) %}{% endstory %}", 'not a filter'],
    'list with' => ["{% story 'A' with ['x'] %}{% endstory %}", 'Its "with" must be a hash'],
    'duplicate' => ["{% story 'A' %}{% endstory %}{% story 'A' %}{% endstory %}", 'Story "A" is already defined'],
    'nested' => ["{% story 'A' %}{% story 'B' %}{% endstory %}{% endstory %}", 'cannot contain another story'],
    'in a block' => ["{% block b %}{% story 'A' %}{% endstory %}{% endblock %}", 'must sit at the top level'],
    'in a macro' => ["{% macro m() %}{% story 'A' %}{% endstory %}{% endmacro %}", 'must sit at the top level'],
    'in an embed' => ["{% story 'A' %}{% embed 'x' %}{% block b %}{% story 'B' %}{% endstory %}{% endblock %}{% endembed %}{% endstory %}", 'cannot contain another story'],
    'unclosed' => ["{% story 'A' %}text", 'Story "A" is missing its {% endstory %}'],
    'component file' => ["{% story 'A' %}{% endstory %}", 'belongs in a component\'s .stories.twig file', 'ui/button.twig'],
]);

it('reports a literal error in with on its own line', function() {
    try {
        parseStories("{% story 'A' with {\n    label: 'x',\n    style: foo,\n} %}{% endstory %}");
        $this->fail('compiled');
    } catch (SyntaxError $e) {
        expect($e->getTemplateLine())->toBe(3);
    }
});

it('keeps separate files independent', function() {
    expect(array_keys(parseStories("{% story 'A' %}{% endstory %}", 'a.stories.twig')))->toBe(['A'])
        ->and(array_keys(parseStories("{% story 'A' %}{% endstory %}", 'b.stories.twig')))->toBe(['A']);
});
