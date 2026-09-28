<?php

declare(strict_types=1);

/*
 * Every test runs inside Craft, booted by markhuot/craft-pest-core from the sandbox root, and
 * inside a database transaction that is rolled back afterwards. Fixtures that must outlive a
 * test (the clViewers group, the clviewer user, site `second`) come from tests/fixtures/setup.sh.
 */

use markhuot\craftpest\test\RefreshesDatabase;
use markhuot\craftpest\test\TestCase;

uses(TestCase::class, RefreshesDatabase::class)->in(__DIR__);
