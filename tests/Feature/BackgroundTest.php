<?php

declare(strict_types=1);

/*
 * The preview background (task 3.4): TS-16 steps 1-3, TN-20, and the viewers' server half of
 * BR-41 (what they open on, and what the address and the preview carry). Picking a background in
 * a browser is TS-16 steps 4-6.
 *
 * `on-dark` lives in tests/fixtures/edge, whose tag sets `background: 'dark'`, so it is read
 * through an index of that root.
 */

use craft\helpers\FileHelper;
use craft\helpers\Json;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\models\Component;
use webdna\componentlibrary\services\Index;
use webdna\componentlibrary\services\Renderer;
use webdna\componentlibrary\services\Viewer;

const BG_VIEWER = 'admin/component-library/@ui:good';

beforeEach(function() {
    $this->withExceptionHandling();
    $this->renderer = ComponentLibrary::getInstance()->getRenderer();
    $token = $this->renderer->createToken('user:' . Craft::$app->getUsers()->getUserByUsernameOrEmail('admin')?->id);

    // A preview render of `@ui:good`'s Secondary story, with `bg` when given.
    $this->render = function(mixed $bg = null) use ($token): string {
        $query = array_filter(['token' => $token, 'component' => '@ui:good', 'story' => 'Secondary', 'bg' => $bg], fn($value) => $value !== null);

        return $this->get('?' . http_build_query($query))->assertOk()->content;
    };
});

function onDarkIndex(): Index
{
    return new Index(['templateDirectories' => [FileHelper::normalizePath(__DIR__ . '/../fixtures/edge')]]);
}

function onDark(): Component
{
    $component = onDarkIndex()->get('@ui:on-dark');
    expect($component)->not->toBeNull();

    return $component;
}

/** The viewer's state from a page, as viewer.js reads it. */
function bgConfig(string $html): array
{
    return Json::decode(html_entity_decode((string)preg_replace('/.*data-cl-viewer="([^"]*)".*/s', '$1', $html)));
}

/** @return array<string,mixed> */
function bgPreviewQuery(string $html): array
{
    expect(preg_match('/<iframe src="([^"]*)"[^>]*data-cl-preview/', $html, $match))->toBe(1);
    parse_str((string)parse_url(html_entity_decode($match[1]), PHP_URL_QUERY), $query);

    return $query;
}

describe('TS-16 the render', function() {
    // Step 1, BR-42: `site` changes nothing. The same page byte for byte was also compared over
    // HTTP with the render from before task 3.4 (build-progress memory).
    it('renders the site’s own background exactly as without one', function() {
        $plain = ($this->render)();

        expect(($this->render)('site'))->toBe($plain)
            ->and($plain)->not->toContain('preview.css')
            ->and($plain)->not->toContain('cl-canvas');
    });

    // Step 2
    it('opens block component with preview.css and wraps the story, unchanged, on light and dark', function(string $bg) {
        $plain = ($this->render)();
        $html = ($this->render)($bg);
        $opening = '<link rel="stylesheet" href="' . htmlspecialchars($this->renderer->previewCssUrl()) . '">'
            . "<div class=\"cl-canvas\" data-cl-canvas=\"$bg\">";

        expect($html)->toContain('<div class="p-4">' . "\n    " . $opening . '<button class="btn btn--secondary">')
            // Take the link and the wrapper out, and it's the plain page.
            ->and(preg_replace('~(</button>\s*)</div>~', '$1', str_replace($opening, '', $html), 1))->toBe($plain);
    })->with(['light', 'dark']);

    it('publishes preview.css, which paints only html and body', function() {
        $dir = ComponentLibrary::getInstance()->getBasePath() . '/web/assets/preview/dist';
        $css = (string)file_get_contents(Craft::$app->getAssetManager()->publish($dir)[0] . '/preview.css');

        expect($this->renderer->previewCssUrl())->toContain('/preview.css')
            ->and($css)->toContain('.cl-canvas {' . "\n    display: contents;")
            ->and($css)->toContain("html:has([data-cl-canvas='light']) body {\n    background: #ffffff !important;")
            ->and($css)->toContain("html:has([data-cl-canvas='dark']) body {\n    background: #111111 !important;")
            ->and(substr_count($css, '!important'))->toBe(2);
    });

    // Step 3: the tag's own background, which a request can change
    it('opens a component on its tag’s background, and bg overrides it', function() {
        // The story renders its handle through the loader, which reads the plugin's index.
        $plugin = ComponentLibrary::getInstance();
        $original = $plugin->getIndex();
        $plugin->set('index', onDarkIndex());

        try {
            $component = onDark();
            $story = $component->stories[array_key_first($component->stories)];
            $render = fn(mixed $bg) => $this->renderer->render($component, $story, [], Renderer::background($component, $bg));

            expect($component->background)->toBe('dark')
                ->and($render(null))->toMatch('~data-cl-canvas="dark"><p class="on-dark">Light text</p>\s*</div>~')
                ->and($render('light'))->toContain('data-cl-canvas="light"><p class="on-dark">')
                // BR-42 as amended in v0.22: `site` is a choice, the site's own background.
                ->and($render('site'))->not->toContain('cl-canvas')
                ->and($render('purple'))->toContain('data-cl-canvas="dark"');
        } finally {
            $plugin->set('index', $original);
        }
    });

    it('settles the background from a closed set, and never returns the request’s text', function(mixed $bg, string $expected) {
        expect(Renderer::background(onDark(), $bg))->toBe($expected);
    })->with([
        'none' => [null, 'dark'],
        'site' => ['site', 'site'],
        'light' => ['light', 'light'],
        'dark' => ['dark', 'dark'],
        'other case' => ['Light', 'dark'],
        'padded' => [' light', 'dark'],
        'array' => [['light'], 'dark'],
        'number' => [1, 'dark'],
    ]);
});

// TN-20
dataset('hostile backgrounds', [
    'markup' => ['<b>x</b>', '<b>x</b>'],
    'script suffix' => ['light<script>', 'light<script>'],
    '5 KB' => [str_repeat('d', 5120), str_repeat('d', 64)],
    'array' => [['dark'], 'bg[0]'],
]);

describe('TN-20 a hostile bg', function() {
    it('renders the component’s own background and echoes nothing', function(mixed $bg, string $needle) {
        $html = ($this->render)($bg);

        expect($html)->toBe(($this->render)())
            ->and($html)->not->toContain($needle);
    })->with('hostile backgrounds');

    it('opens the CP viewer on the component’s own background and keeps it out of the preview', function(mixed $bg, string $needle) {
        $html = $this->actingAs('admin')->get(BG_VIEWER . '?' . http_build_query(['bg' => $bg]))->assertOk()->content;

        expect($html)->toMatch('/data-cl-background="site"\s+aria-pressed="true" tabindex="0"/')
            ->and(bgConfig($html))->toMatchArray(['background' => 'site', 'defaultBackground' => 'site'])
            ->and(bgPreviewQuery($html))->not->toHaveKey('bg')
            ->and($html)->not->toContain($needle);
    })->with('hostile backgrounds');

    it('does the same on a share link', function(mixed $bg, string $needle) {
        $form = webdna\componentlibrary\models\ShareForm::blank();
        $form->label = 'Acme';
        $token = ComponentLibrary::getInstance()->getShares()->create($form, (int)Craft::$app->getUsers()->getUserByUsernameOrEmail('admin')?->id);
        $html = $this->get(webdna\componentlibrary\services\Shares::URL_PATH . "$token/@ui:good?" . http_build_query(['bg' => $bg]))->assertOk()->content;

        expect(bgConfig($html))->toMatchArray(['background' => 'site'])
            ->and(bgPreviewQuery($html))->not->toHaveKey('bg')
            ->and($html)->not->toContain($needle);
    })->with('hostile backgrounds');
});

describe('BR-41 the viewers', function() {
    it('offers Site, Light and Dark as one pressed group after the devices', function() {
        $html = $this->actingAs('admin')->get(BG_VIEWER)->assertOk()->content;

        expect($html)->toContain('<div class="cl-backgrounds" role="group" aria-label="Background">')
            ->and(strpos($html, 'data-cl-rotate'))->toBeLessThan((int)strpos($html, 'data-cl-background="site"'))
            ->and($html)->toMatch('/data-cl-background="site"\s+aria-pressed="true" tabindex="0"/')
            ->and($html)->toMatch('/data-cl-background="light"\s+aria-pressed="false" tabindex="-1"/')
            ->and($html)->toMatch('/data-cl-background="dark"\s+aria-pressed="false" tabindex="-1"/')
            ->and($html)->toContain('<input type="hidden" name="bg" value="site" data-cl-site-background disabled>')
            ->and(bgPreviewQuery($html))->not->toHaveKey('bg');
    });

    // TS-16 step 4's copied address: it reopens on the background, which reaches the preview
    it('opens on the background in the address and sends it to the preview', function() {
        $html = $this->actingAs('admin')->get(BG_VIEWER . '?bg=light&story=Secondary')->assertOk()->content;

        expect($html)->toMatch('/data-cl-background="light"\s+aria-pressed="true" tabindex="0"/')
            ->and(bgConfig($html))->toMatchArray(['background' => 'light', 'defaultBackground' => 'site'])
            ->and(bgPreviewQuery($html))->toMatchArray(['bg' => 'light', 'story' => 'Secondary'])
            // The site switch keeps it. The tree's links drop it, so each component opens on its own.
            ->and($html)->toContain('<input type="hidden" name="bg" value="light" data-cl-site-background >')
            ->and(preg_match_all('/<a href="([^"]*)"[^>]*data-cl-component/', $html, $links))->toBeGreaterThan(1)
            ->and(implode(' ', $links[1]))->not->toContain('bg=');
    });

    it('carries the background on a share link', function() {
        $form = webdna\componentlibrary\models\ShareForm::blank();
        $form->label = 'Acme';
        $token = ComponentLibrary::getInstance()->getShares()->create($form, (int)Craft::$app->getUsers()->getUserByUsernameOrEmail('admin')?->id);
        $html = $this->get(webdna\componentlibrary\services\Shares::URL_PATH . "$token/@ui:good?bg=dark")->assertOk()->content;

        expect($html)->toMatch('/data-cl-background="dark"\s+aria-pressed="true"/')
            ->and(bgPreviewQuery($html))->toMatchArray(['bg' => 'dark']);
    });

    // TS-16 step 5's other half: a component opens on its tag's background, and only a
    // different choice reaches the address or the preview
    it('opens a component on its own background, and sends bg only when it differs', function(mixed $bg, string $background, ?string $sent) {
        $site = Craft::$app->getSites()->getPrimarySite();
        $url = fn(string $handle, array $params) => "/x/$handle?" . http_build_query($params);
        $state = (new Viewer(['index' => onDarkIndex()]))->state($site, '@ui:on-dark', null, null, 'tok', $url, null, null, $bg);
        parse_str((string)parse_url((string)$state['previewUrl'], PHP_URL_QUERY), $query);

        expect($state['background'])->toBe($background)
            ->and($state['defaultBackground'])->toBe('dark')
            ->and($query['bg'] ?? null)->toBe($sent);
    })->with([
        'none' => [null, 'dark', null],
        'its own' => ['dark', 'dark', null],
        'site' => ['site', 'site', 'site'],
        'light' => ['light', 'light', 'light'],
        'hostile' => ['<b>', 'dark', null],
    ]);
});
