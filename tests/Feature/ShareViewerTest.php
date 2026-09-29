<?php

declare(strict_types=1);

/*
 * The share viewer (task 4.2): TS-5 and TS-6, BR-27 and BR-29, and the share page's headers.
 *
 * Every request here is a guest's: the link is the only credential. Links are made through the
 * Shares service, which returns the token, and rolled back with each test. What the page's script
 * does (tabs, settings, the narrow-screen sidebar) is a browser run; here is the server's half.
 */

use craft\db\Query;
use craft\db\Table;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\web\assets\cp\CpAsset;
use craft\web\View;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\controllers\ShareViewerController;
use webdna\componentlibrary\models\ShareForm;
use webdna\componentlibrary\records\ShareRecord;
use webdna\componentlibrary\services\Index;
use webdna\componentlibrary\services\Shares;
use webdna\componentlibrary\services\Viewer;
use webdna\componentlibrary\web\assets\share\CpStylesAsset;
use webdna\componentlibrary\web\assets\share\ShareAsset;
use webdna\componentlibrary\web\assets\viewer\ViewerAsset;
use yii\base\Event;

beforeEach(function() {
    $this->withExceptionHandling();
    $this->shares = ComponentLibrary::getInstance()->getShares();
});

/** A new active link: [token, record]. */
function newShare(string $label = 'Acme'): array
{
    $form = ShareForm::blank();
    $form->label = $label;
    $token = ComponentLibrary::getInstance()->getShares()->create($form, (int)Craft::$app->getUsers()->getUserByUsernameOrEmail('admin')?->id);
    expect($token)->toBeString();

    return [$token, ComponentLibrary::getInstance()->getShares()->find($token)];
}

/** A share page's path below the site: the library, or one component. */
function sharePath(string $token, string $rest = ''): string
{
    return Shares::URL_PATH . $token . ($rest === '' ? '' : "/$rest");
}

/** The preview iframe's src, decoded. */
function sharePreviewSrc(string $html): string
{
    expect(preg_match('/<iframe src="([^"]*)"[^>]*data-cl-preview/', $html, $match))->toBe(1);

    return html_entity_decode($match[1]);
}

/** The scope a preview token's row holds. */
function previewScope(string $src): ?string
{
    parse_str((string)parse_url($src, PHP_URL_QUERY), $query);
    $route = Json::decode((string)(new Query())->select(['route'])->from(Table::TOKENS)->where(['token' => $query['token']])->scalar());

    return $route[1]['scope'] ?? null;
}

function setShareExpiry(ShareRecord $share, string $expires): void
{
    $share->expiresAt = Db::prepareDateForDb(new DateTime($expires));
    $share->save(false);
}

describe('TS-5 use a share link', function() {
    // TS-5 step 1
    it('opens the whole library with no login, headed with the label and expiry', function() {
        [$token, $share] = newShare();
        $expires = DateTimeHelper::toDateTime($share->expiresAt);
        $html = $this->get(sharePath($token))->assertOk()->content;

        expect(Craft::$app->getUser()->getIsGuest())->toBeTrue()
            ->and($html)->toContain('data-cl-share-state="active"')
            ->and($html)->toContain('Acme · expires ' . Craft::$app->getFormatter()->asDate($expires->setTimezone(new DateTimeZone('UTC')), 'long'))
            ->and($html)->toContain('data-cl-component="@ui:good"')
            ->and($html)->toContain('data-cl-component="@ui:nested"')
            ->and($html)->toContain('data-cl-preview');
    });

    // TS-5 step 1, BR-29
    it('shows source and notes, and no file path or root anywhere, for every component', function(string $handle) {
        [$token] = newShare();
        $html = $this->get(sharePath($token, $handle))->assertOk()->content;
        $root = rtrim((string)Craft::getAlias('@root'), '/');

        expect($html)->toContain('id="cl-source"')->and($html)->toContain('id="cl-notes"')
            ->and($html)->not->toContain($root)
            ->and($html)->not->toContain('tests/fixtures')
            ->and($html)->not->toContain('plugins/component-library')
            ->and($html)->not->toContain('.twig</code>')
            ->and($html)->not->toContain('.stories.twig')
            ->and($html)->not->toContain('.config.json');
    })->with(['@ui:good', '@ui:bad-tag', '@ui:nested', '@ui:legacy-button', '@ui:throws']);

    // BR-29: everything but share management
    it('offers no share management and no CP navigation', function() {
        [$token] = newShare();
        $html = $this->get(sharePath($token, '@ui:good'))->assertOk()->content;

        expect($html)->not->toContain('component-library/shares')
            ->and($html)->not->toContain('Share links')
            ->and($html)->not->toContain('global-sidebar')
            ->and($html)->not->toContain('/admin');
    });

    // TS-5 step 2
    it('previews under a share-scoped token that ends with the link, and applies settings', function() {
        [$token, $share] = newShare();
        $props = rawurlencode('{"label":"Hello"}');
        $html = $this->get(sharePath($token, '@ui:good') . "?props=$props")->assertOk()->content;
        $src = sharePreviewSrc($html);

        parse_str((string)parse_url($src, PHP_URL_QUERY), $query);
        $expiry = (new Query())->select(['expiryDate'])->from(Table::TOKENS)->where(['token' => $query['token']])->scalar();

        expect(previewScope($src))->toBe("share:$share->id")
            ->and(DateTimeHelper::toDateTime($expiry))->toBeLessThanOrEqual(DateTimeHelper::toDateTime($share->expiresAt))
            ->and($query)->toMatchArray(['component' => '@ui:good', 'props' => '{"label":"Hello"}'])
            ->and($html)->toContain('value="Hello"');

        $this->get('?' . parse_url($src, PHP_URL_QUERY))->assertOk()->assertSee('Hello');
    });

    // TS-5 step 2: the preview token lasts the link's remaining life when that's under an hour
    it('never outlives a link about to expire', function() {
        [$token, $share] = newShare();
        setShareExpiry($share, '+10 minutes');
        $src = sharePreviewSrc($this->get(sharePath($token))->assertOk()->content);

        parse_str((string)parse_url($src, PHP_URL_QUERY), $query);
        $expiry = DateTimeHelper::toDateTime((new Query())->select(['expiryDate'])->from(Table::TOKENS)->where(['token' => $query['token']])->scalar());

        expect($expiry->getTimestamp())->toBeLessThanOrEqual(DateTimeHelper::toDateTime($share->expiresAt)->getTimestamp());
    });

    // TS-5 step 3
    it('sends no-referrer on the share page, and the preview adds no-store and noindex', function() {
        [$token] = newShare();
        $response = $this->get(sharePath($token, '@ui:good'))->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Cache-Control', 'no-store');

        $this->get('?' . parse_url(sharePreviewSrc($response->content), PHP_URL_QUERY))->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Cache-Control', 'no-store')
            ->assertHeader('X-Robots-Tag', 'noindex');
    });

    // Journey 3 step 2: switch sites, as in the CP
    it('switches site through share links, never CP ones', function() {
        [$token] = newShare();
        $second = Craft::$app->getSites()->getSiteByHandle('second');
        $html = $this->get(sharePath($token, '@ui:good') . '?site=second')->assertOk()->content;
        $home = $this->shares->url($token);

        expect(sharePreviewSrc($html))->toStartWith(rtrim((string)$second->getBaseUrl(), '/') . '/?')
            ->and($html)->toContain('data-cl-site-version')
            ->and($html)->toContain('action="' . htmlspecialchars("$home/@ui:good") . '"')
            ->and($html)->toContain('href="' . htmlspecialchars("$home/@ui:nested?site=second") . '"');
    });

    it('records the visit on the link', function() {
        [$token, $share] = newShare();
        expect($share->lastUsedAt)->toBeNull();

        $this->get(sharePath($token))->assertOk();

        expect(ShareRecord::findOne($share->id)->lastUsedAt)->not->toBeNull();
    });

    // §3 first run
    it('says "Nothing to show yet." on an empty library, with no folders or command', function() {
        [$token] = newShare();
        $plugin = ComponentLibrary::getInstance();
        $index = $plugin->getIndex();
        $plugin->set('index', new Index(['templateDirectories' => [__DIR__ . '/../fixtures/no-such-folder']]));

        try {
            $html = $this->get(sharePath($token))->assertOk()->content;
        } finally {
            $plugin->set('index', $index);
        }

        expect($html)->toContain('Nothing to show yet.')
            ->and($html)->not->toContain('no-such-folder')
            ->and($html)->not->toContain('component-library/make')
            ->and($html)->not->toContain('data-cl-preview');
    });

    // Appendix A row 2: the CP's look, none of its script
    it('loads the CP stylesheets without CpAsset', function() {
        [$token] = newShare();
        $bundles = null;
        $capture = function(Event $event) use (&$bundles) {
            $bundles ??= array_keys($event->sender->assetBundles);
        };
        Event::on(View::class, View::EVENT_END_PAGE, $capture);
        try {
            $html = $this->get(sharePath($token, '@ui:good'))->assertOk()->content;
        } finally {
            Event::off(View::class, View::EVENT_END_PAGE, $capture);
        }

        expect($bundles)->toContain(ShareAsset::class, CpStylesAsset::class)
            ->and($bundles)->not->toContain(CpAsset::class)
            ->and($bundles)->not->toContain(ViewerAsset::class)
            ->and((new ShareAsset())->js)->toBe(['viewer/viewer.js'])
            ->and($html)->not->toContain('window.Craft');
    });
});

describe('TS-15 preview workspace on a share link', function() {
    // TS-15 step 1
    it('is the same workspace as the CP viewer, headed with the label', function() {
        [$token] = newShare('Acme review');
        $html = $this->get(sharePath($token, '@ui:good'))->assertOk()->content;

        expect($html)->toMatch('/<div class="cl-workspace" data-cl-workspace data-cl-share-state="active">/')
            ->and($html)->toContain('<h1 class="cl-heading">Acme review · expires')
            ->and($html)->toContain('<span class="cl-ws-component">Good button</span>');
        foreach (Viewer::DEVICES as $device) {
            expect($html)->toContain("data-cl-device=\"$device\"");
        }
        foreach (['data-cl-rotate', 'data-cl-divider', 'data-cl-tree-toggle', 'data-cl-refresh', 'data-cl-open', 'data-cl-drawer', 'data-cl-drawer-toggle', 'id="cl-tabs"'] as $hook) {
            expect($html)->toContain($hook);
        }
    });

    // TS-15 steps 4 and 8, BR-39: the address's device, kept in share links, never in the preview
    it('opens on the device in the address and keeps it in share links only', function() {
        [$token] = newShare();
        $html = $this->get(sharePath($token, '@ui:good') . '?device=phone&orientation=landscape')->assertOk()->content;

        expect($html)->toMatch('/data-cl-device="phone"\s+aria-pressed="true"/')
            ->and($html)->toContain('data-device="phone" data-orientation="landscape"')
            ->and($html)->toMatch('#href="[^"]*' . Shares::URL_PATH . $token . '/@ui:nested\?[^"]*device=phone&amp;orientation=landscape"#')
            ->and(sharePreviewSrc($html))->not->toContain('device')
            ->and(sharePreviewSrc($html))->not->toContain('orientation');
    });

    // TS-15 step 5, TN-18
    it('treats a device it doesn’t offer as desktop portrait, and never echoes it', function(string $query, string $needle) {
        [$token] = newShare();
        $html = $this->get(sharePath($token, '@ui:good') . "?$query")->assertOk()->content;

        expect($html)->toContain('data-device="desktop" data-orientation="portrait"')
            ->and($html)->toMatch('/data-cl-device="desktop"\s+aria-pressed="true"/')
            ->and($html)->not->toContain($needle);
    })->with('unusable devices');
});

describe('TS-6 revoke and expiry', function() {
    // TS-6 steps 1-2
    it('stops an open preview as soon as the link is revoked, and the address is then unknown', function() {
        [$token, $share] = newShare();
        $preview = '?' . parse_url(sharePreviewSrc($this->get(sharePath($token, '@ui:good'))->assertOk()->content), PHP_URL_QUERY);
        $this->get($preview)->assertOk();

        $this->shares->revoke((int)$share->id);

        $this->get($preview)->assertStatus(403)->assertSee('Preview expired');
        $this->get(sharePath($token, '@ui:good'))->assertStatus(404)
            ->assertSee('This link doesn’t work')
            ->assertDontSee('data-cl-share-state', false)
            ->assertDontSee('Acme');
    });

    // TS-6 step 3, BR-27
    it('refuses an expired link with the expired page', function() {
        [$token, $share] = newShare();
        setShareExpiry($share, '-1 minute');

        $response = $this->get(sharePath($token, '@ui:good'))->assertStatus(410)
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('data-cl-share-state="' . Shares::STATUS_EXPIRED . '"', false)
            ->assertSee('This link has expired')
            ->assertSee('Ask whoever sent it for a new link.')
            ->assertDontSee('data-cl-preview', false)
            ->assertDontSee('Acme');

        expect(ShareRecord::findOne($share->id)->lastUsedAt)->toBeNull()
            ->and($response->content)->not->toContain('data-cl-component');
    });

    // TS-6 step 3, BR-27
    it('is 404 for a token it doesn’t know, of any shape', function(string $token) {
        newShare();

        $this->get(sharePath($token))->assertStatus(404)
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('This link doesn’t work')
            ->assertDontSee('data-cl-share-state', false);
    })->with([
        'right shape' => [str_repeat('A', 43)],
        'too short' => ['abc'],
        'a stored hash' => [str_repeat('a', 64)],
    ]);

    it('is 404 for a component the library doesn’t have, inside an active link', function(string $handle) {
        [$token] = newShare();

        $this->get(sharePath($token, $handle))->assertStatus(404)
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('data-cl-share-state="active"', false)
            ->assertSee('There’s no such component');
    })->with(['@ui:nope', 'shares', '..']);

    it('sends its headers on every state', function(string $state) {
        [$token, $share] = newShare();
        match ($state) {
            'expired' => setShareExpiry($share, '-1 minute'),
            'revoked' => $this->shares->revoke((int)$share->id),
            default => null,
        };

        $response = $this->get(sharePath($state === 'unknown' ? str_repeat('B', 43) : $token));
        foreach (ShareViewerController::HEADERS as $name => $value) {
            $response->assertHeader($name, $value);
        }
    })->with(['active', 'expired', 'revoked', 'unknown']);
});
