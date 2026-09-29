<?php

declare(strict_types=1);

/*
 * The preview render (BR-20 to BR-26): TS-7, TS-10, TN-1 to TN-4 and TN-16.
 *
 * Previews are real site requests carrying a Craft token, as the viewer's iframe sends them.
 * Share rows are made per test and rolled back with it. `restricted` (sandbox fixture) lacks the
 * view permission, so its scope is the refused user.
 */

use craft\db\Table;
use craft\helpers\Db;
use craft\web\View;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\records\ShareRecord;
use webdna\componentlibrary\services\Renderer;
use yii\base\Event;
use yii\base\InvalidArgumentException;
use yii\web\User as YiiUser;

const LAYOUTS = __DIR__ . '/../fixtures/layouts';

beforeEach(function() {
    $this->withExceptionHandling();
    $this->renderer = ComponentLibrary::getInstance()->getRenderer();
});

function renderer(): Renderer
{
    return ComponentLibrary::getInstance()->getRenderer();
}

function userId(string $username): int
{
    return (int)Craft::$app->getUsers()->getUserByUsernameOrEmail($username)?->id;
}

function userScope(string $username = 'admin'): string
{
    return 'user:' . userId($username);
}

/** An active share unless told otherwise. */
function share(string $expires = '+14 days'): ShareRecord
{
    $share = new ShareRecord();
    $share->label = 'Acme';
    $share->tokenHash = hash('sha256', random_bytes(32));
    $share->expiresAt = Db::prepareDateForDb(new DateTime($expires));
    $share->createdById = userId('admin');
    $share->save(false);

    return $share;
}

function shareScope(?ShareRecord $share = null): string
{
    return 'share:' . ($share ?? share())->id;
}

/**
 * A preview request's URI, on a site's base path ('' for the primary site).
 *
 * @param array<string,mixed> $query
 */
function previewUri(?string $token, array $query = [], string $site = ''): string
{
    if (isset($query['props']) && is_array($query['props'])) {
        $query['props'] = json_encode($query['props']);
    }

    return $site . '?' . http_build_query(array_filter(['token' => $token] + $query, fn($value) => $value !== null));
}

function propsFor(string $handle, array $request = [], ?string $story = null): array
{
    $component = ComponentLibrary::getInstance()->getIndex()->get($handle);
    $story = $component->stories[$story ?? array_key_first($component->stories)];

    return renderer()->props($component, $story, $request);
}

describe('tokens (BR-20)', function() {
    it('holds only the scope, and lasts an hour at most', function() {
        $token = $this->renderer->createToken('user:1');
        $row = (new craft\db\Query())->from(Table::TOKENS)->where(['token' => $token])->one();

        expect(json_decode($row['route'], true))->toBe([Renderer::ROUTE, ['scope' => 'user:1']])
            ->and($row['usageLimit'])->toBeNull()
            ->and(strtotime($row['expiryDate'] . ' UTC'))->toBeLessThanOrEqual(time() + 3600)
            ->and(strtotime($row['expiryDate'] . ' UTC'))->toBeGreaterThan(time() + 3500);
    });

    it('ends with a share that ends sooner', function() {
        $token = $this->renderer->createToken('share:1', new DateTime('+10 minutes'));
        $expiry = (new craft\db\Query())->select('expiryDate')->from(Table::TOKENS)->where(['token' => $token])->scalar();

        expect(strtotime($expiry . ' UTC'))->toBeLessThanOrEqual(time() + 600);
    });

    it('refuses a scope that is not user:<id> or share:<id>', function(string $scope) {
        expect(fn() => $this->renderer->createToken($scope))->toThrow(InvalidArgumentException::class);
    })->with(['admin', 'user:', 'user:0', 'user:1 ', 'share:x', 'site:1']);

    it('builds the iframe address on the chosen site', function() {
        $second = Craft::$app->getSites()->getSiteByHandle('second');
        $url = $this->renderer->previewUrl($second, 'abc', '@ui:good', 'Secondary', ['label' => 'Hi']);

        expect($url)->toStartWith(rtrim($second->getBaseUrl(), '/') . '/?token=abc&component=%40ui%3Agood&story=Secondary&props=');
    });
});

// TN-1
describe('without a valid token', function() {
    it('refuses a render with no token', function() {
        $this->get('actions/component-library/render/index?component=@ui:good')
            ->assertStatus(403)->assertSee('data-cl-error')->assertDontSee('btn--primary');
    });

    /*
     * Craft answers an unknown or expired token with a 400 from Application::init(), which runs
     * once per process, so craft-pest's in-process requests never reach it: they fall through to
     * ordinary routing. The 400 itself is checked over HTTP (build-progress memory, task 3.1).
     */
    it('does not render for a garbage token', function() {
        $this->get(previewUri('garbage', ['component' => '@ui:good']))->assertDontSee('btn--primary')->assertDontSee('data-cl-error');
    });

    it('does not render for an expired token', function() {
        $token = $this->renderer->createToken(userScope());
        Db::update(Table::TOKENS, ['expiryDate' => Db::prepareDateForDb(new DateTime('-1 minute'))], ['token' => $token]);

        // In process the expired row outlives Craft's cleanup, so the render refuses it itself.
        $this->get(previewUri($token, ['component' => '@ui:good']))->assertStatus(403)->assertDontSee('btn--primary');
    });
});

// BR-21, TN-2, TN-3
describe('scope recheck', function() {
    it('renders for a user with the view permission', function(string $username) {
        $this->get(previewUri($this->renderer->createToken(userScope($username)), ['component' => '@ui:good']))
            ->assertOk()->assertSee('btn--primary');
    })->with(['admin', 'clviewer']);

    it('refuses a user without the view permission, or who no longer exists', function(string $scope) {
        $this->get(previewUri($this->renderer->createToken($scope), ['component' => '@ui:good']))
            ->assertStatus(403)->assertSee('Preview expired, reload the page.')->assertSee('data-cl-error')->assertDontSee('btn--primary');
    })->with([
        'restricted' => fn() => userScope('restricted'),
        'deleted' => 'user:999999',
    ]);

    // Beyond BR-21: a suspended user's previews stop too.
    it('refuses a user suspended after the token was made', function() {
        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail('clviewer');
        $token = $this->renderer->createToken('user:' . $user->id);
        $this->get(previewUri($token, ['component' => '@ui:good']))->assertOk();

        Craft::$app->getUsers()->suspendUser($user);

        $this->get(previewUri($token, ['component' => '@ui:good']))->assertStatus(403);
    });

    it('renders for an active share, and refuses it once expired or revoked', function() {
        $share = share();
        $token = $this->renderer->createToken(shareScope($share));
        $this->get(previewUri($token, ['component' => '@ui:good']))->assertOk()->assertSee('btn--primary');

        $share->expiresAt = Db::prepareDateForDb(new DateTime('-1 second'));
        $share->save(false);
        $this->get(previewUri($token, ['component' => '@ui:good']))->assertStatus(403)->assertSee('Preview expired');

        $share->expiresAt = Db::prepareDateForDb(new DateTime('+1 day'));
        $share->save(false);
        $this->get(previewUri($token, ['component' => '@ui:good']))->assertOk();
        ComponentLibrary::getInstance()->getShares()->revoke((int)$share->id);
        $this->get(previewUri($token, ['component' => '@ui:good']))->assertStatus(403)->assertSee('Preview expired');
    });

    // TN-4
    it('takes the scope from the token row, never from the query string', function() {
        $shareToken = $this->renderer->createToken(shareScope());

        // A forged user scope on a share token still gets the share's detail-free error panel.
        $this->get(previewUri($shareToken, ['component' => '@ui:throws', 'scope' => userScope()]))
            ->assertStatus(500)->assertDontSee('max()');

        $this->get('actions/component-library/render/index?' . http_build_query(['component' => '@ui:good', 'scope' => userScope()]))
            ->assertStatus(403)->assertDontSee('btn--primary');
    });
});

describe('what renders', function() {
    it('renders the named story, or the first', function() {
        $token = $this->renderer->createToken(userScope());

        $this->get(previewUri($token, ['component' => '@ui:good']))->assertOk()->assertSee('>Save</button>');
        $this->get(previewUri($token, ['component' => '@ui:good', 'story' => 'Secondary']))->assertOk()->assertSee('btn--secondary')->assertSee('>Cancel</button>');
    });

    it('renders a story body from its stories file, and only that story (BR-9)', function() {
        $token = $this->renderer->createToken(userScope());

        $this->get(previewUri($token, ['component' => '@ui:good', 'story' => 'In a toolbar']))
            ->assertOk()->assertSee('<div class="toolbar"><button class="btn btn--primary">Undo</button>')->assertDontSee('>Cancel<');
    });

    it('returns 404 for an unknown component or story, and never reads a path', function(array $query) {
        $this->get(previewUri($this->renderer->createToken(userScope()), $query))
            ->assertStatus(404)->assertSee('data-cl-error')->assertDontSee('<button');
    })->with([
        'unknown handle' => [['component' => '@ui:nope']],
        'path' => [['component' => 'ui/good.twig']],
        'traversal' => [['component' => '../../config/db.php']],
        'no component' => [[]],
        'unknown story' => [['component' => '@ui:good', 'story' => 'Nope']],
        'story as array' => [['component' => '@ui:good', 'story' => ['Default']]],
    ]);

    // BR-22
    it('renders the site that served the request, whatever the query says', function() {
        $token = $this->renderer->createToken(userScope());
        $sites = Craft::$app->getSites();

        // craft-pest keeps one app across requests and never re-detects the site, so this sets
        // what a request on `second`'s base URL would. The real URL is checked over HTTP.
        try {
            $sites->setCurrentSite('second');
            $this->get(previewUri($token, ['component' => '@ui:good']))->assertOk()->assertSee('class="second"');
        } finally {
            $sites->setCurrentSite($sites->getPrimarySite());
        }

        $this->get(previewUri($token, ['component' => '@ui:good', 'site' => 'second']))->assertOk()->assertDontSee('class="second"')->assertSee('btn--primary');
    });

    // BR-25
    it('puts the story and its viewClass in the plugin’s bare layout by default', function() {
        $this->get(previewUri($this->renderer->createToken(userScope()), ['component' => '@ui:good']))
            ->assertOk()->assertSee('<meta name="robots" content="noindex">')->assertSee('<div class="p-4">');
    });

    // BR-26
    it('sends the preview headers on every response', function(array $query, int $status) {
        $response = $this->get(previewUri($this->renderer->createToken(userScope()), $query))->assertStatus($status);
        $primary = parse_url(Craft::$app->getSites()->getPrimarySite()->getBaseUrl());

        $response->assertHeader('Cache-Control', 'no-store')
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        // The CP origin as the request sees it, and the primary site's, and nothing else.
        expect($response->getHeaders()->get('Content-Security-Policy'))
            ->toMatch('~^frame-ancestors https?://[^\s/]+( https?://[^\s/]+)?$~')
            ->toContain(" {$primary['scheme']}://{$primary['host']}");
    })->with([
        'ok' => [['component' => '@ui:good'], 200],
        'not found' => [['component' => '@ui:nope'], 404],
        'bad props' => [['component' => '@ui:good', 'props' => '[1]'], 400],
        'error' => [['component' => '@ui:throws'], 500],
    ]);
});

// BR-25, through Renderer::render() so a test layout can sit on a swapped templates path
describe('layouts', function() {
    it('fills a v1 layout’s component and viewClass blocks', function() {
        $view = Craft::$app->getView();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        $path = $view->getTemplatesPath();
        $good = ComponentLibrary::getInstance()->getIndex()->get('@ui:good');

        try {
            $view->setTemplatesPath(realpath(LAYOUTS));
            $this->renderer->layout = 'v1';

            expect($this->renderer->render($good, $good->stories['Default'], propsFor('@ui:good')))
                ->toContain('<body class="v1-layout">')
                ->toContain('<main class="p-4"><button class="btn btn--primary">Save</button>')
                // A component without a viewClass leaves the layout's own block.
                ->and($this->renderer->render($good->with(['viewClass' => null]), $good->stories['Default'], []))
                ->toContain('<main class="fallback-class">');
        } finally {
            $view->setTemplatesPath($path);
            $this->renderer->layout = null;
        }
    });

    /*
     * Craft routes any public site template by its path, template roots included. A fresh View,
     * as a real request has: Craft caches resolved names per View without the public-only flag,
     * so after an earlier test rendered a preview, the router would find the cached path.
     */
    it('never serves the plugin’s templates as front-end pages', function(string $uri) {
        $view = Craft::$app->getView();
        Craft::$app->set('view', Craft::createObject(View::class));

        try {
            $this->get($uri)->assertStatus(404);
        } finally {
            Craft::$app->set('view', $view);
        }
    })->with([
        'component-library/_render/layout',
        'component-library/_render/page',
        'component-library/_render/error',
        'component-library/viewer/index',
        'component-library/shares/index',
        'component-library/site/_render/layout',
    ]);

    it('picks a layout per site, with the bare layout for a site not listed', function() {
        try {
            $this->renderer->layout = ['default' => '_preview/default', 'other' => '_preview/other'];
            expect($this->renderer->layout())->toBe('_preview/default');

            Craft::$app->getSites()->setCurrentSite('second');
            expect($this->renderer->layout())->toBe(Renderer::DEFAULT_LAYOUT);

            $this->renderer->layout = '_preview/all';
            expect($this->renderer->layout())->toBe('_preview/all');
        } finally {
            Craft::$app->getSites()->setCurrentSite(Craft::$app->getSites()->getPrimarySite());
            $this->renderer->layout = null;
        }
    });
});

// BR-24
describe('prop coercion', function() {
    it('merges defaults, then the story, then the request', function() {
        $props = propsFor('@ui:good', ['label' => 'Hello'], 'Secondary');

        expect((string)$props['label'])->toBe('Hello')
            ->and($props['style'])->toBe('secondary')
            ->and($props['count'])->toBe(3);
    });

    it('hands every request string over as escaped Markup', function() {
        $props = propsFor('@ui:raw-prop', ['body' => '<b>"x"</b>', 'data' => ['a' => ['b' => '<i>']]]);

        expect($props['body'])->toBeInstanceOf(Twig\Markup::class)
            ->and((string)$props['body'])->toBe('&lt;b&gt;&quot;x&quot;&lt;/b&gt;')
            ->and((string)$props['data']['a']['b'])->toBe('&lt;i&gt;');
    });

    it('coerces each type, and drops what does not fit', function(string $name, mixed $value, mixed $expected) {
        $props = propsFor('@ui:good', [$name => $value]);
        $actual = $props[$name] instanceof Twig\Markup ? (string)$props[$name] : $props[$name];

        expect($actual)->toBe($expected);
    })->with([
        'string' => ['label', 'Hi', 'Hi'],
        'string from a number' => ['label', 42, '42'],
        'string at the limit' => ['label', str_repeat('a', 2000), str_repeat('a', 2000)],
        'string over the limit' => ['label', str_repeat('a', 2001), 'Save'],
        'string as array' => ['label', ['x'], 'Save'],
        'text at the limit' => ['body', str_repeat('a', 10000), str_repeat('a', 10000)],
        'text over the limit' => ['body', str_repeat('a', 10001), null],
        'bool' => ['disabled', true, true],
        'bool as string' => ['disabled', 'true', true],
        'bool nonsense' => ['disabled', 'maybe', false],
        'number' => ['count', 7, 7],
        'number as string' => ['count', '2.5', 2.5],
        'number nonsense' => ['count', '7 apples', 3],
        'select option' => ['style', 'secondary', 'secondary'],
        'select unknown' => ['style', 'danger', 'primary'],
        'select hash key' => ['size', 'lg', 'lg'],
        'select label is not a value' => ['size', 'Large', null],
        'json depth 5' => ['attrs', [[[[[1]]]]], [[[[[1]]]]]],
        'json depth 6' => ['attrs', [[[[[[1]]]]]], ['data-x' => 1]],
    ]);

    it('drops undeclared props, and HTML-special keys in json', function() {
        $props = propsFor('@ui:raw-prop', ['foo' => 'bar', 'data' => ['<k>' => 'v', 'ok' => 'v']]);

        expect($props)->not->toHaveKey('foo')
            ->and(array_keys($props['data']))->toBe(['ok']);
    });

    it('refuses props that are over 8 KB or not a JSON object', function(mixed $raw) {
        expect(fn() => $this->renderer->decodeProps($raw))->toThrow(InvalidArgumentException::class);
    })->with([
        'over 8 KB' => [json_encode(['label' => str_repeat('a', 8200)])],
        'list' => ['[1,2]'],
        'string' => ['"x"'],
        'broken' => ['{"label":'],
        'array param' => [['label' => 'x']],
    ]);

    it('takes an object at the 8 KB limit, and nothing as nothing', function() {
        $raw = json_encode(['label' => str_repeat('a', 8192 - 12)]);

        expect(strlen($raw))->toBe(8192)
            ->and($this->renderer->decodeProps($raw))->toHaveKey('label')
            ->and($this->renderer->decodeProps(null))->toBe([])
            ->and($this->renderer->decodeProps(''))->toBe([]);
    });
});

// BR-16
describe('legacy includes', function() {
    it('renders {include:@handle} in a legacy default', function() {
        $icon = propsFor('@ui:legacy-button')['icon'];

        expect($icon)->toBeInstanceOf(Twig\Markup::class)
            ->and(trim((string)$icon))->toBe('<button class="btn btn--primary">Save</button>');
    });

    it('never renders one sent in the request', function() {
        $icon = propsFor('@ui:legacy-button', ['icon' => '{include:@ui:good}'])['icon'];

        expect((string)$icon)->toBe('{include:@ui:good}');
    });
});

// TS-7
describe('hostile values', function() {
    it('prints request HTML as text, even through |raw', function() {
        $token = $this->renderer->createToken(shareScope());

        $this->get(previewUri($token, ['component' => '@ui:raw-prop']))->assertOk()->assertSee('<em>From the file</em>');
        $this->get(previewUri($token, ['component' => '@ui:raw-prop', 'props' => ['body' => '<script>x</script>', 'data' => ['t' => '<img src=x onerror=alert(1)>']]]))
            ->assertOk()
            ->assertSee('&lt;script&gt;x&lt;/script&gt;')
            ->assertDontSee('<script>x')
            ->assertDontSee('<img src=x');
    });

    it('never resolves placeholders or Twig in a prop', function(string $label) {
        $this->get(previewUri($this->renderer->createToken(shareScope()), ['component' => '@ui:good', 'props' => ['label' => $label]]))
            ->assertOk()->assertSee(htmlspecialchars($label))->assertDontSee('49');
    })->with(['{ref:entry:1:title}', '{{ 7*7 }}', '{% include "@ui:throws" %}']);

    it('ignores a site parameter and undeclared props', function() {
        $token = $this->renderer->createToken(shareScope());
        $plain = $this->get(previewUri($token, ['component' => '@ui:good', 'props' => ['label' => 'Hi']]))->assertOk();
        $noisy = $this->get(previewUri($token, ['component' => '@ui:good', 'site' => '../../etc', 'props' => ['label' => 'Hi', 'foo' => '<b>']]))->assertOk();

        expect($noisy->content)->toBe($plain->content);
    });

    it('refuses props over 8 KB with a 400', function() {
        $this->get(previewUri($this->renderer->createToken(shareScope()), ['component' => '@ui:good', 'props' => ['label' => str_repeat('a', 9 * 1024)]]))
            ->assertStatus(400)->assertSee('data-cl-error')->assertDontSee('<button');
    });
});

// TS-10
describe('readable errors', function() {
    it('shows the message, template and line in user scope, with no server path', function() {
        $response = $this->get(previewUri($this->renderer->createToken(userScope()), ['component' => '@ui:throws']))
            ->assertStatus(500)
            ->assertSee('data-cl-error')
            ->assertSee('max(): Argument #1 ($value) must contain at least one element')
            ->assertSee('@ui:throws, line 6');

        expect($response->content)->not->toContain(Craft::getAlias('@root'))
            ->and($response->content)->not->toContain('/plugins/component-library/');
    });

    it('shows only a sentence in share scope', function() {
        $this->get(previewUri($this->renderer->createToken(shareScope()), ['component' => '@ui:throws']))
            ->assertStatus(500)
            ->assertSee('This component couldn’t be shown.')
            ->assertDontSee('max()')
            ->assertDontSee('@ui:throws');
    });

    it('leaves every other component working', function() {
        $token = $this->renderer->createToken(userScope());
        $this->get(previewUri($token, ['component' => '@ui:throws']))->assertStatus(500);

        $this->get(previewUri($token, ['component' => '@ui:good']))->assertOk()->assertSee('btn--primary');
    });

    it('takes absolute paths out of any message', function() {
        $root = Craft::getAlias('@root');
        $templates = Craft::$app->getPath()->getSiteTemplatesPath();
        $detail = $this->renderer->describe(new RuntimeException("Can't read $templates/x.twig, $root/config/db.php or /opt/secret/y.twig"));

        expect($detail['message'])->toBe("Can't read x.twig, config/db.php or …/y.twig")
            ->and($detail['template'])->toBeNull();
    });
});

// TN-16, BR-23
describe('guest identity', function() {
    it('renders as a guest without signing the browser out', function() {
        $logouts = 0;
        $handler = function() use (&$logouts) {
            $logouts++;
        };
        Event::on(YiiUser::class, YiiUser::EVENT_BEFORE_LOGOUT, $handler);

        try {
            $this->actingAs('admin');
            $session = Craft::$app->getSession();
            $idParam = Craft::$app->getUser()->idParam;
            $session->set($idParam, userId('admin'));

            $this->get(previewUri($this->renderer->createToken(userScope()), ['component' => '@ui:guest']))
                ->assertOk()->assertSee('<p class="whoami">guest</p>');

            expect($logouts)->toBe(0)
                ->and($session->get($idParam))->toBe(userId('admin'));
        } finally {
            Event::off(YiiUser::class, YiiUser::EVENT_BEFORE_LOGOUT, $handler);
        }
    });
});
