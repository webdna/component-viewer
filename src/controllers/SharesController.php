<?php

namespace webdna\componentviewer\controllers;

use Craft;
use craft\web\Controller;
use webdna\componentviewer\ComponentViewer;
use webdna\componentviewer\models\ShareForm;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Share-link management (§6): the list and create form, create, copy, and revoke (which deletes).
 */
class SharesController extends Controller
{
    /** The flash that carries a new link's address to the one page that shows it (BR-27). */
    public const FLASH_URL = 'component-viewer-share-url';

    public function beforeAction($action): bool
    {
        // BR-2: both permissions, checked here and not in the template.
        $this->requireCpRequest();
        $this->requirePermission(ComponentViewer::PERMISSION_VIEW);
        $this->requirePermission(ComponentViewer::PERMISSION_MANAGE_SHARES);

        // BR-3: CSRF validation stays on. parent::beforeAction() is where Yii checks the token.
        return parent::beforeAction($action);
    }

    /** A failed create comes back here with its form, so the errors and values show. */
    public function actionIndex(?ShareForm $share = null): Response
    {
        return $this->renderTemplate('component-viewer/shares/index', [
            'rows' => ComponentViewer::getInstance()->getShares()->rows(),
            'share' => $share ?? ShareForm::blank(),
            // Read and removed in one go, so a reload never shows it again.
            'newUrl' => Craft::$app->getSession()->getFlash(self::FLASH_URL, null, true),
            'minExpiry' => ShareForm::day(1),
            'maxExpiry' => ShareForm::day(ShareForm::MAX_DAYS),
        ]);
    }

    public function actionCreate(): ?Response
    {
        $this->requirePostRequest();

        $shares = ComponentViewer::getInstance()->getShares();
        $form = new ShareForm([
            'label' => (string)$this->request->getBodyParam('label', ''),
            'expiry' => (string)$this->request->getBodyParam('expiry', ''),
        ]);

        $token = $shares->create($form, (int)Craft::$app->getUser()->getId());
        if ($token === null) {
            return $this->asModelFailure($form, Craft::t('component-viewer', 'Couldn’t create the share link.'), 'share');
        }

        Craft::$app->getSession()->setFlash(self::FLASH_URL, $shares->url($token));

        return $this->asSuccess(
            Craft::t('component-viewer', 'Share link created.'),
            redirect: 'component-viewer/shares',
        );
    }

    /**
     * BR-45: an active link's address, for *Copy link*'s prompt. POST, so CSRF-checked, behind
     * both permissions (beforeAction). 404 for any link that can't be copied.
     *
     * @throws NotFoundHttpException
     */
    public function actionUrl(): Response
    {
        $this->requirePostRequest();

        $url = ComponentViewer::getInstance()->getShares()->copyUrl((int)$this->request->getRequiredBodyParam('id'));
        if ($url === null) {
            throw new NotFoundHttpException(Craft::t('component-viewer', 'There’s no such share link.'));
        }

        return $this->asJson(['url' => $url]);
    }

    public function actionRevoke(): Response
    {
        $this->requirePostRequest();

        $id = (int)$this->request->getRequiredBodyParam('id');
        if (!ComponentViewer::getInstance()->getShares()->revoke($id)) {
            throw new NotFoundHttpException(Craft::t('component-viewer', 'There’s no such share link.'));
        }

        return $this->asSuccess(
            Craft::t('component-viewer', 'Share link revoked.'),
            redirect: 'component-viewer/shares',
        );
    }
}
