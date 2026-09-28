<?php

declare(strict_types=1);

/*
 * The CP viewer (task 3.2): the tree, controls, stories, site switch, source, notes, the address
 * as the view's state, empty states and the §7 test hooks (BR-2, BR-22, BR-34, BR-35).
 *
 * What the page's script does in a browser (TS-2, TS-3 steps 1-2, TS-14) is a browser run. Here
 * the server's half is proved: the page a copied address opens, and the preview it points at.
 */

use craft\db\Query;
use craft\db\Table;
use craft\helpers\Cp;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\View;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\services\Index;
use webdna\componentlibrary\services\Renderer;
use webdna\componentlibrary\services\Viewer;
use webdna\componentlibrary\web\assets\viewer\ViewerAsset;
use yii\base\Event;

const VIEWER = 'admin/component-library';
const GOOD = VIEWER . '/@ui:good';

beforeEach(function() {
    $this->withExceptionHandling();
});

/** The preview iframe's src, decoded, from a viewer page. */
function previewSrc(string $html): string
{
    expect(preg_match('/<iframe src="([^"]*)"[^>]*data-cl-preview/', $html, $match))->toBe(1);

    return html_entity_decode($match[1]);
}

/** @return array<string,string> */
function previewQuery(string $html): array
{
    parse_str((string)parse_url(previewSrc($html), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * The viewer's own markup, the tree and the component, with the one-off preview token taken
 * out, for comparing two loads. The rest of the CP page has ids that change per request.
 */
function viewerMarkup(string $html): string
{
    $tree = (string)strstr((string)strstr($html, '<div class="cl-search">'), '</nav>', true);
    $component = (string)strstr($html, '<div class="cl-viewer"');
    $component = substr($component, 0, (int)strpos($component, '<div id="cl-source"'));
    expect($tree)->not->toBe('')->and($component)->not->toBe('');

    return (string)preg_replace('/token=[A-Za-z0-9_-]+/', '', $tree . $component);
}

function viewerUserId(string $username): int
{
    return (int)Craft::$app->getUsers()->getUserByUsernameOrEmail($username)?->id;
}

/** Runs $test with the plugin's viewer component replaced. */
function withViewer(Viewer $viewer, Closure $test): void
{
    $plugin = ComponentLibrary::getInstance();
    $original = $plugin->getViewer();
    $plugin->set('viewer', $viewer);

    try {
        $test();
    } finally {
        $plugin->set('viewer', $original);
    }
}

// TS-1 step 3 "with the tree", and the first component when none is named
it('opens on the first component of the tree', function() {
    $html = $this->actingAs('clviewer')->get(VIEWER)->assertOk()->content;

    $first = array_key_first(ComponentLibrary::getInstance()->getIndex()->all());
    expect($html)->toContain('data-cl-component="@ui:good"')
        ->and($html)->toContain('data-cl-component="@ui:legacy-button"')
        ->and(previewQuery($html)['component'])->toBe($first)
        ->and($html)->toMatch('/class="sel" aria-current="page" data-cl-component="' . preg_quote($first) . '"/');
});

it('lists every component, grouped by category', function() {
    $html = $this->actingAs('admin')->get(GOOD)->assertOk()->content;

    foreach (array_keys(ComponentLibrary::getInstance()->getIndex()->all()) as $handle) {
        expect($html)->toContain('data-cl-component="' . $handle . '"');
    }
    expect($html)->toContain('<li class="heading" data-cl-category><span>ui</span></li>')
        ->and($html)->toContain('<label for="cl-search" class="visually-hidden">Search components</label>');
});

it('refuses a handle the index does not have', function(string $path) {
    $this->actingAs('admin')->get($path)->assertStatus(404);
})->with([VIEWER . '/@ui:nope', VIEWER . '/@ui:good:nope', VIEWER . '/@nope']);

it('does not route a path that is not a handle to the viewer', function() {
    $this->actingAs('admin')->get(VIEWER . '/ui:good')->assertStatus(404);
});

// §7 hooks, BR-34
it('gives each prop a labelled control and each story a button', function() {
    $html = $this->actingAs('admin')->get(GOOD)->assertOk()->content;

    foreach (['label', 'count', 'offset', 'disabled', 'attrs', 'style', 'size', 'body', 'icon'] as $prop) {
        expect($html)->toContain("data-cl-prop=\"$prop\"")
            ->and($html)->toContain("<label id=\"cl-prop-$prop-label\" for=\"cl-prop-$prop\"");
    }
    expect($html)->toContain('data-cl-type="bool"')->and($html)->toContain('type="checkbox"')
        ->and($html)->toContain('data-cl-type="number"')->and($html)->toContain('type="number"')
        ->and($html)->toContain('<textarea')
        ->and($html)->toContain('<option value="sm">Small</option>');

    expect($html)->toContain('data-cl-story="Default"' . "\n" . '                            aria-pressed="true"')
        ->and($html)->toContain('data-cl-story="Secondary"' . "\n" . '                            aria-pressed="false"')
        ->and($html)->toContain('data-cl-story="In a toolbar"');
});

// BR-20, BR-34
it('points the preview at the primary site with a token scoped to the viewing user', function() {
    $html = $this->actingAs('clviewer')->get(GOOD)->assertOk()->content;
    $query = previewQuery($html);

    $base = rtrim((string)Craft::$app->getSites()->getPrimarySite()->getBaseUrl(), '/') . '/?';
    expect(previewSrc($html))->toStartWith($base)
        ->and($query['component'])->toBe('@ui:good')
        ->and($query['story'])->toBe('Default')
        ->and($query)->not->toHaveKey('props')
        ->and($html)->toContain('title="Preview of Good button, example “Default”"');

    $route = (new Query())->select('route')->from(Table::TOKENS)
        ->where(['token' => $query[Craft::$app->getConfig()->getGeneral()->tokenParam]])->scalar();
    expect(Json::decode($route))->toBe([Renderer::ROUTE, ['scope' => 'user:' . viewerUserId('clviewer')]]);
});

// TS-2 step 3: the copied address reopens the view
it('restores the story and settings from the address', function() {
    $props = rawurlencode('{"label":"Hello","foo":"dropped"}');
    $html = $this->actingAs('admin')->get(GOOD . "?story=Secondary&props=$props")->assertOk()->content;
    $query = previewQuery($html);

    expect($query['story'])->toBe('Secondary')
        ->and($query['props'])->toBe('{"label":"Hello"}')
        ->and($html)->toContain('title="Preview of Good button, example “Secondary”"')
        ->and($html)->toContain('data-cl-story="Secondary"' . "\n" . '                            aria-pressed="true"')
        ->and($html)->toMatch('/id="cl-prop-label"[^>]*value="Hello"|value="Hello"[^>]*id="cl-prop-label"/')
        ->and($html)->toContain('<option value="secondary" selected>')
        ->and($html)->not->toContain('dropped');

    $config = Json::decode(html_entity_decode((string)preg_replace('/.*data-cl-viewer="([^"]*)".*/s', '$1', $html)));
    expect($config['story'])->toBe('Secondary')
        ->and($config['overrides'])->toBe(['label' => 'Hello'])
        ->and($config['stories']['Secondary'])->toMatchArray(['label' => 'Cancel', 'style' => 'secondary', 'count' => 3])
        ->and($config['types'])->toMatchArray(['label' => 'string', 'disabled' => 'bool', 'attrs' => 'json'])
        ->and($config['previewUrl'])->toBe(previewSrc($html));
});

it('falls back to the first story and no settings for values it cannot use', function(string $query) {
    $html = $this->actingAs('admin')->get(GOOD . $query)->assertOk()->content;

    expect(previewQuery($html)['story'])->toBe('Default')
        ->and(previewQuery($html))->not->toHaveKey('props');
})->with([
    'unknown story' => '?story=Nope',
    'story as a list' => '?story[]=Secondary',
    'props not JSON' => '?props=nope',
    'props a list' => '?props=' . rawurlencode('["x"]'),
    'props over 8 KB' => '?props=' . rawurlencode(json_encode(['label' => str_repeat('x', 9000)])),
    'props undeclared only' => '?props=' . rawurlencode('{"foo":1}'),
]);

it('falls back to the first story in the file, whatever its name', function() {
    $html = $this->actingAs('admin')->get(VIEWER . '/@ui:nested?story=Nope')->assertOk()->content;

    expect(previewQuery($html)['story'])->toBe('Confirm');
});

it('escapes a request value in the controls and the page state', function() {
    $props = rawurlencode('{"label":"<script>alert(1)</script>"}');
    $html = $this->actingAs('admin')->get(GOOD . "?props=$props")->assertOk()->content;

    expect($html)->not->toContain('<script>alert(1)')
        ->and($html)->toContain('value="&lt;script&gt;alert(1)&lt;/script&gt;"');
});

// TS-3 steps 1-2, BR-22
it('previews on the chosen site and marks its own version', function() {
    $second = Craft::$app->getSites()->getSiteByHandle('second');
    $html = $this->actingAs('admin')->get(GOOD . '?site=second')->assertOk()->content;

    expect(previewSrc($html))->toStartWith(rtrim((string)$second->getBaseUrl(), '/') . '/?')
        ->and($html)->toContain('data-cl-site-version')
        ->and($html)->toContain('<h2 class="cl-title">Good button (second site)</h2>')
        ->and($html)->toContain('<option value="second" selected>')
        ->and($html)->toContain('href="' . UrlHelper::cpUrl('component-library/@ui:good', ['site' => 'second']) . '"');

    $shared = $this->actingAs('admin')->get(GOOD)->assertOk()->content;
    expect($shared)->not->toContain('data-cl-site-version')
        ->and($shared)->toContain('<h2 class="cl-title">Good button</h2>');
});

// Craft's cpUrl() adds the requested site to any CP URL without `site`. craft-pest keeps
// Cp::requestedSite() from the first request, so it's set here as a real `?site=second` sets it.
it('keeps the handle in every link when the CP has a requested site', function() {
    $requested = new ReflectionProperty(Cp::class, '_requestedSite');
    $before = $requested->getValue();
    $requested->setValue(null, Craft::$app->getSites()->getSiteByHandle('second'));

    try {
        $html = $this->actingAs('admin')->get(GOOD . '?site=second')->assertOk()->content;
    } finally {
        $requested->setValue(null, $before);
    }

    expect($html)->toContain('href="' . UrlHelper::cpUrl('component-library/@ui:guest', ['site' => 'second']) . '"')
        ->and($html)->toMatch('#<form class="cl-site" method="get" action="[^"?]+/component-library/@ui:good"#');
});

it('splits a GET form address into its action and hidden fields', function() {
    expect(Viewer::formFor('https://x.test/index.php?p=admin/component-library/@ui:good&site=a&story=b&props=c&x=1'))->toBe([
        'action' => 'https://x.test/index.php',
        'hidden' => ['p' => 'admin/component-library/@ui:good', 'x' => '1'],
    ])->and(Viewer::formFor('https://x.test/admin/component-library/@ui:good'))->toBe([
        'action' => 'https://x.test/admin/component-library/@ui:good',
        'hidden' => [],
    ]);
});

it('keeps the current site after listing another', function() {
    $this->actingAs('admin')->get(GOOD . '?site=second')->assertOk();

    expect(Craft::$app->getSites()->getCurrentSite()->primary)->toBeTrue();
});

// BR-22: a site is a site only once Craft returns it
it('treats an unknown site as the primary site', function(string $site) {
    $plain = $this->actingAs('admin')->get(GOOD)->assertOk()->content;
    $hostile = $this->actingAs('admin')->get(GOOD . '?site=' . rawurlencode($site))->assertOk()->content;

    expect(viewerMarkup($hostile))->toBe(viewerMarkup($plain));
})->with(['../../etc', 'default/../second', '', 'SECOND ']);

// BR-35, TN-15
it('offers a site switch only when there is another site', function() {
    $html = $this->actingAs('admin')->get(GOOD)->assertOk()->content;
    expect($html)->toContain('data-cl-site-form')->and($html)->toContain('<label for="cl-site">Site</label>');

    $single = new class() extends Viewer {
        public function sites(): array
        {
            return [Craft::$app->getSites()->getPrimarySite()];
        }
    };

    withViewer($single, function() {
        $html = $this->actingAs('admin')->get(GOOD)->assertOk()->content;

        expect($html)->not->toContain('data-cl-site-form')
            ->and($html)->not->toContain('id="cl-site"')
            ->and(previewQuery($html)['component'])->toBe('@ui:good');
    });
});

it('shows the source files, notes and problems', function() {
    $html = $this->actingAs('admin')->get(GOOD)->assertOk()->content;

    expect($html)->toContain('<code class="light">plugins/component-library/tests/fixtures/templates/ui/good.twig</code>')
        ->and($html)->toContain('<code class="light">plugins/component-library/tests/fixtures/templates/ui/good.stories.twig</code>')
        ->and($html)->toContain('{% story &#039;Secondary&#039; with')
        ->and($html)->toContain('A <strong>fixture</strong> component')
        ->and($html)->not->toContain('class="cl-problems"');

    $legacy = $this->actingAs('admin')->get(VIEWER . '/@ui:legacy-button')->assertOk()->content;
    expect($legacy)->toContain('Legacy settings <code class="light">plugins/component-library/tests/fixtures/templates/legacy/button/button.config.json</code>');

    $bad = $this->actingAs('admin')->get(VIEWER . '/@ui:bad-tag')->assertOk()->content;
    expect($bad)->toContain('class="cl-problems"')->and($bad)->toContain('<code>CL001</code>');
});

// §3 first run
it('says when a component has no settings or notes', function() {
    $html = $this->actingAs('admin')->get(VIEWER . '/@ui:guest')->assertOk()->content;

    expect($html)->toContain('This component has no settings.')
        ->and($html)->toContain('This component has no notes.')
        ->and($html)->not->toContain('data-cl-prop=')
        ->and($html)->toContain('data-cl-story="Default"');
});

it('shows the empty state with the folders searched and the create command', function() {
    $empty = sys_get_temp_dir() . '/cl-empty-' . uniqid();
    mkdir($empty);

    try {
        withViewer(new Viewer(['index' => new Index(['templateDirectories' => [$empty]])]), function() use ($empty) {
            $html = $this->actingAs('admin')->get(VIEWER)->assertOk()->content;

            expect($html)->toContain('data-cl-empty')
                ->and($html)->toContain('No components yet')
                ->and($html)->toContain("<code>$empty</code>")
                ->and($html)->toContain('php craft component-library/make ui/button')
                ->and($html)->toContain('href="' . Viewer::FORMAT_GUIDE . '"')
                ->and($html)->not->toContain('data-cl-preview')
                ->and($html)->not->toContain('id="tabs"');
        });
    } finally {
        rmdir($empty);
    }
});

it('shows the tabs only with a component', function() {
    $html = $this->actingAs('admin')->get(GOOD)->assertOk()->content;

    foreach (['cl-settings', 'cl-examples', 'cl-source', 'cl-notes'] as $panel) {
        expect($html)->toContain("aria-controls=\"$panel\"")
            ->and($html)->toContain("id=\"$panel\" role=\"tabpanel\" aria-labelledby=\"tab-$panel\"");
    }
});

// craft-pest keeps one View, which prints a bundle only on the first page that registers it, so
// the tags themselves are checked over HTTP. Here: the page registers it, as a module.
it('loads the viewer script as a module', function() {
    // The view drops its bundles once the page is out, so they're read as it ends.
    $bundle = null;
    $capture = function(Event $event) use (&$bundle) {
        $bundle ??= $event->sender->assetBundles[ViewerAsset::class] ?? null;
    };
    Event::on(View::class, View::EVENT_END_PAGE, $capture);
    try {
        $this->actingAs('admin')->get(GOOD)->assertOk();
    } finally {
        Event::off(View::class, View::EVENT_END_PAGE, $capture);
    }

    expect($bundle)->toBeInstanceOf(ViewerAsset::class)
        ->and($bundle->js)->toBe(['viewer.js'])
        ->and($bundle->jsOptions)->toBe(['type' => 'module'])
        ->and($bundle->css)->toBe(['viewer.css']);
});
