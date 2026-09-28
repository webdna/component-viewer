<?php

declare(strict_types=1);

/*
 * Every test runs inside Craft, booted by markhuot/craft-pest-core from the sandbox root, and
 * inside a database transaction that is rolled back afterwards. Fixtures that must outlive a
 * test (the clViewers group, the clviewer user, site `second`) come from tests/fixtures/setup.sh.
 */

use markhuot\craftpest\test\RefreshesDatabase;
use markhuot\craftpest\test\TestCase;
use PHPUnit\Util\ExcludeList;
use yii\BaseYii;

uses(TestCase::class, RefreshesDatabase::class)->in(__DIR__);

/*
 * Yii silences warnings it expects with `@`, such as FileCache's filemtime() on every cache miss.
 * PHPUnit drops a silenced warning only when its file is on the exclude list, and Collision's
 * printer counts the rest, so any test that makes a request would report warnings. Unsilenced
 * warnings from Yii still report.
 */
ExcludeList::addDirectory(dirname((new ReflectionClass(BaseYii::class))->getFileName()));
