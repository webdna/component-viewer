<?php

namespace webdna\componentlibrary\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

use webdna\componentlibrary\helpers\ComponentViewerHelper;

class ComponentViewerController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * @var string[]
     */
    protected int|bool|array $allowAnonymous = ['get-component-info', 'get-component-history'];

    public function actionIndex(): Response
    {
        return $this->asJson(['success' => true]);
    }

    public function actionGetComponentInfo(): Response
    {
        // get componentId from request params
        $componentId = Craft::$app->request->getParam('componentId');
        $variant = Craft::$app->request->getParam('variant');
        $site = Craft::$app->request->getParam('site');
        $component = ComponentViewerHelper::getComponent($componentId, $variant, $site);

        return $this->asJson($component);
    }

}
