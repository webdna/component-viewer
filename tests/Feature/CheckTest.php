<?php

declare(strict_types=1);

/*
 * The library check (TS-11, BR-31): `craft component-library/check [--strict]`.
 *
 * Step 1 runs over the fixture roots plus tests/fixtures/edge, where every broken case lives, with
 * `@templates` pointed at tests/fixtures so each path prints relative to it. Other cases run over a
 * throwaway root of their own.
 */

use craft\helpers\FileHelper;
use markhuot\craftpest\console\TestableResponse;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\console\controllers\CheckController;
use webdna\componentlibrary\services\Index;
use yii\console\ExitCode;

const CHECK_FIXTURES = __DIR__ . '/../fixtures';

beforeEach(function() {
    $this->plugin = ComponentLibrary::getInstance();
    $this->original = $this->plugin->getIndex();
    $this->templates = Craft::getAlias('@templates');
    $this->tmp = null;

    // Runs the command as the CLI would, with --strict if asked, capturing what it prints.
    $this->check = function(bool $strict = false): TestableResponse {
        $controller = new CheckController('check', $this->plugin);
        $controller->strict = $strict;
        $this->stdout = '';
        $filter = stream_filter_append(STDOUT, 'craftpest.buffer.stdout');
        try {
            $exitCode = $controller->actionIndex();
        } finally {
            stream_filter_remove($filter);
        }

        return new TestableResponse($exitCode, $this->stdout, '');
    };

    // Indexes the given roots, with the fixture sites folder unless told otherwise.
    $this->useIndex = function(array $roots, ?string $sites = null): void {
        $this->plugin->set('index', new Index(['templateDirectories' => $roots, 'sites' => $sites, 'legacy' => true]));
    };

    // A throwaway root, written from relative path => contents, which is also @templates.
    $this->root = function(array $files): string {
        $this->tmp = FileHelper::normalizePath(Craft::$app->getPath()->getTempPath() . '/cl-check-' . uniqid());
        foreach ($files as $relative => $contents) {
            FileHelper::writeToFile("$this->tmp/$relative", $contents);
        }
        Craft::setAlias('@templates', $this->tmp);

        return $this->tmp;
    };
});

afterEach(function() {
    $this->plugin->set('index', $this->original);
    $this->original->invalidate();
    Craft::setAlias('@templates', $this->templates);
    if ($this->tmp !== null) {
        FileHelper::removeDirectory($this->tmp);
    }
});

describe('TS-11 step 1: the fixture roots with the edge cases', function() {
    beforeEach(function() {
        $fixtures = FileHelper::normalizePath(CHECK_FIXTURES);
        ($this->useIndex)(["$fixtures/templates", "$fixtures/edge"], "$fixtures/templates/_sites");
        Craft::setAlias('@templates', $fixtures);
        $this->response = ($this->check)();
    });

    it('fails, listing a broken tag, a missing handle and an unresolved placeholder', function() {
        $this->response->assertExitCode(ExitCode::UNSPECIFIED_ERROR)
            ->assertSee('CL001 templates/ui/bad-tag.twig:3 The "component" tag takes literal values only.')
            ->assertSee("CL003 edge/pages/references.twig:2 It includes @ui:missing, which isn't a component on any site.")
            ->assertSee('CL006 edge/legacy/placeholders.config.json');
    });

    it('prints one line per problem, then the count', function() {
        $lines = explode("\n", rtrim($this->response->stdout, "\n"));
        $count = array_pop($lines);

        expect($count)->toBe(count($lines) . ' problems')
            ->and($lines)->each->toMatch('/^CL00[1-8] \S+ \S.*$/');
    });

    it('reports every code the fixtures hold', function(string $line) {
        $this->response->assertSee($line);
    })->with([
        'CL001 stories' => 'CL001 edge/other/broken.stories.twig:1 ',
        'CL001 loose handle' => 'CL001 edge/loose.twig Its path gives the handle @loose',
        'CL002 by folder' => 'CL002 edge/cards/card/card.twig Also claims @cards:card, which edge/cards/card.twig already has',
        'CL002 by tag' => 'CL002 edge/other/teaser.twig Also claims @cards:card',
        'CL003 include()' => "CL003 edge/pages/references.twig:5 It includes @ui:absent, which isn't",
        'CL003 embed' => "CL003 edge/pages/references.twig:6 It embeds @ui:hollow, which isn't",
        'CL003 extends' => "CL003 edge/pages/extended.twig:2 It extends @layouts:missing, which isn't",
        'CL004' => "CL004 edge/refs/panel.stories.twig:4 A story includes @ui:nowhere, which isn't",
        'CL005 broken' => 'CL005 edge/legacy/broken.config.json',
        'CL005 not JSON' => 'CL005 edge/legacy/not-json.config.json',
        'CL007' => 'CL007 edge/legacy/converted.config.json',
        'CL008' => "CL008 edge/pages/references.twig:4 Its include name starts @blocks: but is built at run time, so the check can't tell whether it exists.",
    ]);

    it('ignores known handles, ignore missing, paths and namespaces', function(string $line) {
        $this->response->assertDontSee("edge/pages/references.twig:$line ");
    })->with(['3', '7', '8', '9', '10', '11', '12']);

    it('reports a problem once, however many sites share the component', function() {
        expect(Craft::$app->getSites()->getTotalSites())->toBeGreaterThan(1)
            ->and(substr_count($this->response->stdout, 'templates/ui/bad-tag.twig:3'))->toBe(1);
    });

    it('prints no server path', function() {
        $this->response->assertDontSee(FileHelper::normalizePath(CHECK_FIXTURES));
    });
});

describe('TS-11 step 2: a clean library', function() {
    it('passes with 0 problems over good alone', function() {
        $templates = FileHelper::normalizePath(CHECK_FIXTURES . '/templates');
        ($this->useIndex)([($this->root)([
            'ui/good.twig' => file_get_contents("$templates/ui/good.twig"),
            'ui/good.stories.twig' => file_get_contents("$templates/ui/good.stories.twig"),
        ])]);

        $response = ($this->check)();

        $response->assertExitCode(ExitCode::OK);
        expect($response->stdout)->toBe("0 problems\n");
    });
});

describe('BR-31', function() {
    it('passes on warnings alone, and fails on them with --strict', function() {
        ($this->useIndex)([($this->root)([
            'ui/card.twig' => "{% component { name: 'Card' } %}\n{% include '@blocks:' ~ type %}\n",
        ])]);

        ($this->check)()->assertExitCode(ExitCode::OK)->assertSee('CL008 ui/card.twig:2 ');
        ($this->check)(true)->assertExitCode(ExitCode::UNSPECIFIED_ERROR)->assertSee('1 problems');
    });

    it('offers --strict on the command line', function() {
        expect((new CheckController('check', $this->plugin))->options('index'))->toContain('strict');
    });

    it('knows a handle that only one site has', function() {
        $root = ($this->root)([
            '_sites/second/ui/only.twig' => "{% component { name: 'Only on second' } %}\n",
            'pages/home.twig' => "{% include '@ui:only' %}\n",
        ]);
        ($this->useIndex)([$root], "$root/_sites");

        ($this->check)()->assertExitCode(ExitCode::OK)->assertSee('0 problems');
    });

    it('reads the files on disk, not a cached index', function() {
        Craft::$app->getConfig()->getGeneral()->devMode = false;
        $root = ($this->root)(['ui/card.twig' => "{% component { name: 'Card' } %}\n"]);
        ($this->useIndex)([$root]);

        // Warm every site's index, since the check reads them all.
        $sites = Craft::$app->getSites();
        foreach ($sites->getAllSites(true) as $site) {
            $sites->setCurrentSite($site);
            $this->plugin->getIndex()->all();
        }
        $sites->setCurrentSite($sites->getPrimarySite());

        FileHelper::writeToFile("$root/ui/card.twig", "{% component { name: card } %}\n");

        ($this->check)()->assertExitCode(ExitCode::UNSPECIFIED_ERROR)->assertSee('CL001 ui/card.twig:1 ');
    });

    it('leaves the current site as it was', function() {
        // The primary site, because the check visits it first: ending on the last site would show.
        $primary = Craft::$app->getSites()->getPrimarySite();
        Craft::$app->getSites()->setCurrentSite($primary);
        ($this->useIndex)([($this->root)(['ui/card.twig' => "{% component { name: 'Card' } %}\n"])]);

        ($this->check)();

        expect(Craft::$app->getSites()->getCurrentSite()->handle)->toBe($primary->handle);
    });

    it('scans a root outside the templates folder', function() {
        $root = ($this->root)(['pages/home.twig' => "{% include '@ui:nowhere' %}\n"]);
        ($this->useIndex)([$root]);
        Craft::setAlias('@templates', FileHelper::normalizePath(CHECK_FIXTURES . '/layouts'));

        ($this->check)()->assertExitCode(ExitCode::UNSPECIFIED_ERROR)->assertSee('CL003 ');
    });
});

describe('B5 #4: the sandbox fixture config', function() {
    it('reports only the deliberate bad-tag fixture', function() {
        $response = $this->console(CheckController::class, 'index');

        $response->assertExitCode(ExitCode::UNSPECIFIED_ERROR)
            ->assertSee('CL001 plugins/component-library/tests/fixtures/templates/ui/bad-tag.twig:3 ')
            ->assertSee("\n1 problems\n");
    });
});
