<?php

namespace webdna\componentlibrary\controllers;

use craft\web\Controller;
use webdna\componentlibrary\ComponentLibrary;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Share-link management (§6). The list, form and share service arrive in task 4.1; the gate is
 * final.
 */
class SharesController extends Controller
{
    public function beforeAction($action): bool
    {
        // BR-2: both permissions, checked here and not in the template.
        $this->requireCpRequest();
        $this->requirePermission(ComponentLibrary::PERMISSION_VIEW);
        $this->requirePermission(ComponentLibrary::PERMISSION_MANAGE_SHARES);

        // BR-3: CSRF validation stays on. parent::beforeAction() is where Yii checks the token.
        return parent::beforeAction($action);
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('component-library/shares/index');
    }

    public function actionCreate(): Response
    {
        $this->requirePostRequest();

        throw new ServerErrorHttpException('Share links are not built yet (task 4.1).');
    }

    public function actionRevoke(): Response
    {
        $this->requirePostRequest();

        throw new ServerErrorHttpException('Share links are not built yet (task 4.1).');
    }
}
