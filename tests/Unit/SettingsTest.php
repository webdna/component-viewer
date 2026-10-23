<?php

declare(strict_types=1);

/*
 * The settings: config/component-viewer.php, which Craft hands to the plugin as its settings. With
 * no settings model Craft logged "Attempting to set settings on a plugin that doesn't have
 * settings" on every request (2.0.0). The sandbox config is the one tests/fixtures/setup.sh writes.
 */

use craft\helpers\FileHelper;
use webdna\componentviewer\ComponentViewer;
use webdna\componentviewer\models\Settings;
use webdna\componentviewer\services\Index;
use yii\log\Logger;

it('has a settings model holding the config file', function() {
    $settings = ComponentViewer::getInstance()->getSettings();

    expect($settings)->toBeInstanceOf(Settings::class)
        ->and($settings->templateDirectories)->toBe(['@root/plugins/component-viewer/tests/fixtures/templates'])
        ->and($settings->legacy)->toBeTrue()
        ->and($settings->icons)->toBe('@root/plugins/component-viewer/tests/fixtures/icons')
        ->and($settings->layout)->toBeNull();
});

it('configures the index from the settings', function() {
    $index = ComponentViewer::getInstance()->getIndex();

    expect($index->templateDirectories)->toBe(['@root/plugins/component-viewer/tests/fixtures/templates'])
        ->and($index->sites)->toBe('@root/plugins/component-viewer/tests/fixtures/templates/_sites')
        ->and($index->legacy)->toBeTrue()
        ->and(FileHelper::normalizePath(Craft::getAlias($index->icons)))->toEndWith('tests/fixtures/icons');
});

it('leaves a service default alone when its setting is unset', function() {
    expect(ComponentViewer::getInstance()->getRenderer()->layout)->toBeNull()
        ->and((new Index())->templateDirectories)->toBe(['@templates/_components']);
});

it('takes settings without a warning', function() {
    $logger = Craft::getLogger();
    $before = count($logger->messages);

    ComponentViewer::getInstance()->setSettings(['icons' => '@root/plugins/component-viewer/tests/fixtures/icons']);

    $warnings = array_filter(
        array_slice($logger->messages, $before),
        fn(array $message) => $message[1] === Logger::LEVEL_WARNING && str_contains((string)$message[0], 'have settings'),
    );
    expect($warnings)->toBe([]);
});
