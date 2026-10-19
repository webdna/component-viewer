<?php

namespace webdna\componentviewer\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use webdna\componentviewer\ComponentViewer;
use webdna\componentviewer\web\assets\viewer\ViewerAsset;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * The CP viewer (§6): `admin/component-viewer[/<handle>]?story=&site=&props=&device=&orientation=&bg=`.
 *
 * The address holds the whole view, so a copied link reopens it (AC-2). `site` only picks the
 * preview's base URL and whose index is listed, and only once Craft returns a site for it (BR-22).
 */
class ViewerController extends Controller
{
    public function beforeAction($action): bool
    {
        // BR-2: enforced here, not only by Craft's plugin-handle gate, which covers
        // admin/component-viewer/… but not admin/actions/component-viewer/….
        $this->requireCpRequest();
        $this->requirePermission(ComponentViewer::PERMISSION_VIEW);

        return parent::beforeAction($action);
    }

    public function actionIndex(?string $handle = null): Response
    {
        $plugin = ComponentViewer::getInstance();
        $viewer = $plugin->getViewer();
        $request = $this->request;

        $site = $viewer->site($request->getQueryParam('site'));
        $token = $plugin->getRenderer()->createToken('user:' . Craft::$app->getUser()->getId());
        $url = fn(string $handle, array $params) => UrlHelper::cpUrl("component-viewer/$handle", $params);
        $state = $viewer->state($site, $handle, $request->getQueryParam('story'), $request->getQueryParam('props'), $token, $url,
            $request->getQueryParam('device'), $request->getQueryParam('orientation'), $request->getQueryParam('bg'));

        if ($state === null) {
            throw new NotFoundHttpException(Craft::t('component-viewer', 'There’s no component {handle} on {site}.', [
                'handle' => $handle,
                'site' => $site->getName(),
            ]));
        }

        $this->view->registerAssetBundle(ViewerAsset::class);

        return $this->renderTemplate('component-viewer/viewer/index', $state + [
            'showPaths' => true,
            'formatGuide' => $viewer::FORMAT_GUIDE,
        ]);
    }
}
