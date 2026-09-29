<?php

namespace webdna\componentlibrary\controllers;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\View;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\services\Shares;
use webdna\componentlibrary\services\Viewer;
use webdna\componentlibrary\web\assets\share\ShareAsset;
use yii\web\Response;

/**
 * The share viewer (§6): `<primary site>/component-library/share/<token>[/<handle>]?story=&site=&props=&device=&orientation=&bg=`.
 *
 * Anonymous: the link is the credential (BR-27). It shows what the CP viewer does, with the same
 * templates, but no paths or roots (BR-29), and previews under a `share:` token, so revoking the
 * link stops them too (BR-21). Unknown links, revoked ones included, are 404, and expired ones 410.
 *
 * The route param isn't called `token`: Yii copies route params into the query string, where
 * Craft reads `token` as a token of its own.
 */
class ShareViewerController extends Controller
{
    /**
     * Sent with every share page, whatever its state. The address is the credential, so no
     * request from the page (the preview included) may carry it in a Referer (BR-29), and no
     * cache may keep a page after its link is revoked.
     */
    public const HEADERS = [
        'Referrer-Policy' => 'no-referrer',
        'Cache-Control' => 'no-store',
        'X-Robots-Tag' => 'noindex, nofollow',
    ];

    protected array|bool|int $allowAnonymous = ['index' => self::ALLOW_ANONYMOUS_LIVE];

    public function actionIndex(string $shareToken, ?string $handle = null): Response
    {
        $this->requireSiteRequest();

        foreach (self::HEADERS as $name => $value) {
            $this->response->headers->set($name, $value);
        }

        $plugin = ComponentLibrary::getInstance();
        $shares = $plugin->getShares();
        $share = $shares->find($shareToken);

        if ($share === null) {
            return $this->message(404, null, Craft::t('component-library', 'This link doesn’t work'),
                Craft::t('component-library', 'Check that the address is complete, or ask whoever sent it for a new link.'));
        }

        // A revoked link has no row, so it was the unknown page above.
        $status = $shares->status($share);
        if ($status === Shares::STATUS_EXPIRED) {
            return $this->message(410, $status, Craft::t('component-library', 'This link has expired'),
                Craft::t('component-library', 'Ask whoever sent it for a new link.'));
        }

        $shares->markUsed($share);

        $viewer = $plugin->getViewer();
        $request = $this->request;
        $home = $shares->url($shareToken);
        $expires = DateTimeHelper::toDateTime($share->expiresAt) ?: null;

        $site = $viewer->site($request->getQueryParam('site'));
        $token = $plugin->getRenderer()->createToken('share:' . $share->id, $expires);
        $url = fn(string $handle, array $params) => UrlHelper::urlWithParams("$home/$handle", $params);
        $state = $viewer->state($site, $handle, $request->getQueryParam('story'), $request->getQueryParam('props'), $token, $url,
            $request->getQueryParam('device'), $request->getQueryParam('orientation'), $request->getQueryParam('bg'));

        if ($state === null) {
            return $this->message(404, $status, Craft::t('component-library', 'There’s no such component'),
                Craft::t('component-library', 'It may have been renamed or removed.'), $home);
        }

        $this->view->registerAssetBundle(ShareAsset::class);

        return $this->renderTemplate('component-library/share/index', $state + [
            'share' => $share,
            'expires' => $expires,
            'home' => $home,
            'showPaths' => false,
            'formatGuide' => Viewer::FORMAT_GUIDE,
        ], View::TEMPLATE_MODE_CP);
    }

    /** A plain page: one sentence and a suggestion (§6 Screens). */
    private function message(int $status, ?string $state, string $heading, string $text, ?string $home = null): Response
    {
        $this->response->setStatusCode($status);
        $this->view->registerAssetBundle(ShareAsset::class);

        return $this->renderTemplate('component-library/share/_message', [
            'state' => $state,
            'heading' => $heading,
            'text' => $text,
            'home' => $home,
        ], View::TEMPLATE_MODE_CP);
    }
}
