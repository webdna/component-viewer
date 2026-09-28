<?php

declare(strict_types=1);

/*
 * Every test runs inside Craft, booted by markhuot/craft-pest-core from the sandbox root, and
 * inside a database transaction that is rolled back afterwards. Fixtures that must outlive a
 * test (the clViewers group, the clviewer user, site `second`) come from tests/fixtures/setup.sh.
 */

use craft\web\View;
use markhuot\craftpest\test\RefreshesDatabase;
use markhuot\craftpest\test\TestCase;
use PHPUnit\Util\ExcludeList;
use Twig\Environment;
use Twig\Node\ModuleNode;
use Twig\Source;
use webdna\componentlibrary\models\Component;
use webdna\componentlibrary\models\Story;
use webdna\componentlibrary\twig\ComponentNode;
use webdna\componentlibrary\twig\StoryNode;
use yii\BaseYii;

uses(TestCase::class, RefreshesDatabase::class)->in(__DIR__);

/*
 * Yii silences warnings it expects with `@`, such as FileCache's filemtime() on every cache miss.
 * PHPUnit drops a silenced warning only when its file is on the exclude list, and Collision's
 * printer counts the rest, so any test that makes a request would report warnings. Unsilenced
 * warnings from Yii still report.
 */
ExcludeList::addDirectory(dirname((new ReflectionClass(BaseYii::class))->getFileName()));

/*
 * Helpers for the tag tests. Inline sources are test inputs only: the plugin itself never renders
 * a string (B3).
 */

const FIXTURES = __DIR__ . '/fixtures/templates';

/** Craft's own Twig environment for a template mode, which is where the tags must be registered. */
function twigIn(string $mode): Environment
{
    $view = Craft::$app->getView();
    $view->setTemplateMode($mode);

    return $view->getTwig();
}

function parseTemplate(string $source, string $name, string $mode = View::TEMPLATE_MODE_SITE): ModuleNode
{
    $twig = twigIn($mode);

    return $twig->parse($twig->tokenize(new Source($source, $name)));
}

function parseTag(string $source, string $name = 'inline.twig', string $mode = View::TEMPLATE_MODE_SITE): ?Component
{
    return ComponentNode::find(parseTemplate($source, $name, $mode))?->getComponent();
}

/**
 * @return array<string,Story>
 */
function parseStories(string $source, string $name = 'inline.stories.twig', string $mode = View::TEMPLATE_MODE_SITE): array
{
    return StoryNode::findAll(parseTemplate($source, $name, $mode));
}
