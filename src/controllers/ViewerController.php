<?php

namespace webdna\componentlibrary\controllers;

use craft\web\Controller;
use webdna\componentlibrary\ComponentLibrary;
use yii\web\Response;

/**
 * The CP viewer (§6). The tree, controls and preview arrive in task 3.2; the gate is final.
 */
class ViewerController extends Controller
{
    public function beforeAction($action): bool
    {
        // BR-2: enforced here, not only by Craft's plugin-handle gate, which covers
        // admin/component-library/… but not admin/actions/component-library/….
        $this->requireCpRequest();
        $this->requirePermission(ComponentLibrary::PERMISSION_VIEW);

        return parent::beforeAction($action);
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('component-library/viewer/index');
    }
}
