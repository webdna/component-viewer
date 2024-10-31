<?php
namespace webdna\componentlibrary;

use Craft;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\helpers\Json;
use craft\web\View;
use yii\base\Event;
use yii\base\Module;

use webdna\componentlibrary\base\Formatters;
use webdna\componentlibrary\twig\ComponentLibraryLoader;
use webdna\componentlibrary\twig\ComponentLibraryTwigExtension;

class ComponentLibrary extends Module
{
	/**
	 * Initializes the module.
	 */
	public function init()
	{
		// Set a @modules alias pointed to the modules/ directory
		Craft::setAlias('@modules', __DIR__);

		// Set the controllerNamespace based on whether this is a console or web request
		if (Craft::$app->getRequest()->getIsConsoleRequest()) {
			$this->controllerNamespace = 'webdna\\componentlibrary\\console\\controllers';
		} else {
			$this->controllerNamespace = 'webdna\\componentlibrary\\controllers';
		}

		parent::init();

		$this->setComponents([
			'formatters' => Formatters::class,
		]);
		
		Craft::setAlias('@webdna/componentlibrary', $this->getBasePath());
		
		Craft::$app->onInit(function() {
			$view = Craft::$app->view;
			$view->getTwig()->setLoader(new ComponentLibraryLoader($view));
			$view->registerTwigExtension(new ComponentLibraryTwigExtension());
			
			Event::on(
				View::class, 
				View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS, 
				function(RegisterTemplateRootsEvent $e) {
					if (is_dir($baseDir = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates')) {
						$e->roots[$this->id] = $baseDir;
					}
				}
			);
		});
	}
}
