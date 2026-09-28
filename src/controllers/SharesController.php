<?php

namespace webdna\componentlibrary\controllers;

use Craft;
use craft\web\Controller;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\models\ShareForm;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Share-link management (§6): the list and create form, create, and cancel.
 */
class SharesController extends Controller
{
    /** The flash that carries a new link's address to the one page that shows it (BR-27). */
    public const FLASH_URL = 'component-library-share-url';

    public function beforeAction($action): bool
    {
        // BR-2: both permissions, checked here and not in the template.
        $this->requireCpRequest();
        $this->requirePermission(ComponentLibrary::PERMISSION_VIEW);
        $this->requirePermission(ComponentLibrary::PERMISSION_MANAGE_SHARES);

        // BR-3: CSRF validation stays on. parent::beforeAction() is where Yii checks the token.
        return parent::beforeAction($action);
    }

    /** A failed create comes back here with its form, so the errors and values show. */
    public function actionIndex(?ShareForm $share = null): Response
    {
        return $this->renderTemplate('component-library/shares/index', [
            'rows' => ComponentLibrary::getInstance()->getShares()->rows(),
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

        $shares = ComponentLibrary::getInstance()->getShares();
        $form = new ShareForm([
            'label' => (string)$this->request->getBodyParam('label', ''),
            'expiry' => (string)$this->request->getBodyParam('expiry', ''),
        ]);

        $token = $shares->create($form, (int)Craft::$app->getUser()->getId());
        if ($token === null) {
            return $this->asModelFailure($form, Craft::t('component-library', 'Couldn’t create the share link.'), 'share');
        }

        Craft::$app->getSession()->setFlash(self::FLASH_URL, $shares->url($token));

        return $this->asSuccess(
            Craft::t('component-library', 'Share link created.'),
            redirect: 'component-library/shares',
        );
    }

    public function actionRevoke(): Response
    {
        $this->requirePostRequest();

        $id = (int)$this->request->getRequiredBodyParam('id');
        if (!ComponentLibrary::getInstance()->getShares()->revoke($id)) {
            throw new NotFoundHttpException(Craft::t('component-library', 'There’s no such share link.'));
        }

        return $this->asSuccess(
            Craft::t('component-library', 'Share link cancelled.'),
            redirect: 'component-library/shares',
        );
    }
}
