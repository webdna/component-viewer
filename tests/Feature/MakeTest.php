<?php

declare(strict_types=1);

/*
 * The make command (TS-12, BR-32): `craft component-library/make <category>/<name> [--root=<n>] [--folder]`.
 *
 * Each test runs over two throwaway roots, `one` and `two`, inside a folder that is also
 * `@templates`, so the paths the command prints are relative to it.
 */

use craft\helpers\FileHelper;
use markhuot\craftpest\console\TestableResponse;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\console\controllers\CheckController;
use webdna\componentlibrary\console\controllers\MakeController;
use webdna\componentlibrary\services\Index;
use yii\console\ExitCode;

const MAKE_COMPONENT = <<<'TWIG'
{% component {
    name: 'Badge',
    status: 'wip',
} %}
<div class="badge"></div>

TWIG;

const MAKE_STORIES = "{% story 'Default' %}{% endstory %}\n";

beforeEach(function() {
    $this->withExceptionHandling();
    $this->plugin = ComponentLibrary::getInstance();
    $this->original = $this->plugin->getIndex();
    $this->templates = Craft::getAlias('@templates');
    $this->devMode = Craft::$app->getConfig()->getGeneral()->devMode;

    $this->tmp = FileHelper::normalizePath(Craft::$app->getPath()->getTempPath() . '/cl-make-' . uniqid());
    FileHelper::createDirectory($this->tmp);
    Craft::setAlias('@templates', $this->tmp);

    // Indexes roots one and two, with a sites folder if asked.
    $this->useIndex = function(bool $sites = false): Index {
        $index = new Index(['templateDirectories' => ["$this->tmp/one", "$this->tmp/two"], 'sites' => $sites ? "$this->tmp/_sites" : null]);
        $this->plugin->set('index', $index);

        return $index;
    };
    $this->index = ($this->useIndex)();

    // Runs the command as the CLI would, with its options, capturing both streams.
    $this->make = function(string $path, array $options = []): TestableResponse {
        $this->stdout = '';
        $this->stderr = '';
        $controller = new MakeController('make', $this->plugin);
        foreach ($options as $option => $value) {
            $controller->$option = $value;
        }
        $out = stream_filter_append(STDOUT, 'craftpest.buffer.stdout');
        $err = stream_filter_append(STDERR, 'craftpest.buffer.stderr');
        try {
            $exitCode = $controller->actionIndex($path);
        } finally {
            stream_filter_remove($out);
            stream_filter_remove($err);
        }

        return new TestableResponse($exitCode, $this->stdout, $this->stderr);
    };

    // Every file under the throwaway folder, relative to it.
    $this->files = function(): array {
        $files = array_map(fn(string $file) => substr(FileHelper::normalizePath($file), strlen($this->tmp) + 1), FileHelper::findFiles($this->tmp));
        sort($files);

        return $files;
    };
});

afterEach(function() {
    $this->plugin->set('index', $this->original);
    $this->original->invalidate();
    Craft::setAlias('@templates', $this->templates);
    Craft::$app->getConfig()->getGeneral()->devMode = $this->devMode;
    FileHelper::removeDirectory($this->tmp);
});

describe('TS-12', function() {
    it('step 1: writes the two files into the first root, and the check reports 0 problems', function() {
        ($this->make)('ui/badge')
            ->assertExitCode(ExitCode::OK)
            ->assertSee("Created one/ui/badge.twig\nCreated one/ui/badge.stories.twig\n");

        expect(($this->files)())->toBe(['one/ui/badge.stories.twig', 'one/ui/badge.twig'])
            ->and(file_get_contents("$this->tmp/one/ui/badge.twig"))->toBe(MAKE_COMPONENT)
            ->and(file_get_contents("$this->tmp/one/ui/badge.stories.twig"))->toBe(MAKE_STORIES);

        $this->stdout = '';
        $out = stream_filter_append(STDOUT, 'craftpest.buffer.stdout');
        try {
            $exitCode = (new CheckController('check', $this->plugin))->actionIndex();
        } finally {
            stream_filter_remove($out);
        }
        expect($exitCode)->toBe(ExitCode::OK)->and($this->stdout)->toBe("0 problems\n");
    });

    it('step 2: the viewer lists @ui:badge with a Default story', function() {
        ($this->make)('ui/badge')->assertExitCode(ExitCode::OK);

        $component = $this->index->get('@ui:badge');
        expect($component)->not->toBeNull()
            ->and($component->name)->toBe('Badge')
            ->and($component->status)->toBe('wip')
            ->and($component->errors)->toBe([])
            ->and(array_keys($component->stories))->toBe(['Default']);

        $html = $this->actingAs('admin')->get('admin/component-library/@ui:badge')->assertOk()->content;
        expect($html)->toMatch('/data-cl-component="@ui:badge"[^>]*>\s*<span class="label">Badge<\/span>/')
            ->and($html)->toMatch('/data-cl-story="Default"\s+aria-pressed="true"/')
            ->and($html)->toContain('data-cl-preview');
    });

    it('step 3: refuses to make it again and leaves both files unchanged', function() {
        ($this->make)('ui/badge')->assertExitCode(ExitCode::OK);
        file_put_contents("$this->tmp/one/ui/badge.twig", 'edited');

        ($this->make)('ui/badge')
            ->assertExitCode(ExitCode::CANTCREAT)
            ->assertSee("one/ui/badge.twig already exists, so nothing was written.\n");

        expect($this->stdout)->toBe('')
            ->and(file_get_contents("$this->tmp/one/ui/badge.twig"))->toBe('edited')
            ->and(file_get_contents("$this->tmp/one/ui/badge.stories.twig"))->toBe(MAKE_STORIES);
    });
});

it('is seen by the next request even when the cached index is not checked against the disk', function() {
    Craft::$app->getConfig()->getGeneral()->devMode = false;
    expect($this->index->get('@ui:badge'))->toBeNull();

    ($this->make)('ui/badge')->assertExitCode(ExitCode::OK);
    $this->index->reset();

    expect($this->index->get('@ui:badge'))->not->toBeNull();
});

it('refuses, writing nothing, when only the stories file exists', function() {
    FileHelper::writeToFile("$this->tmp/one/ui/badge.stories.twig", 'mine');

    ($this->make)('ui/badge')
        ->assertExitCode(ExitCode::CANTCREAT)
        ->assertSee("one/ui/badge.stories.twig already exists, so nothing was written.\n");

    expect(($this->files)())->toBe(['one/ui/badge.stories.twig']);
});

it('nests the files in a folder with --folder, under the same handle', function() {
    ($this->make)('ui/badge', ['folder' => true])
        ->assertExitCode(ExitCode::OK)
        ->assertSee("Created one/ui/badge/badge.twig\nCreated one/ui/badge/badge.stories.twig\n");

    expect(($this->files)())->toBe(['one/ui/badge/badge.stories.twig', 'one/ui/badge/badge.twig'])
        ->and(file_get_contents("$this->tmp/one/ui/badge/badge.twig"))->toBe(MAKE_COMPONENT)
        ->and($this->index->get('@ui:badge')?->path)->toBe("$this->tmp/one/ui/badge/badge.twig");
});

it('refuses the other layout of a handle the same root already has', function(bool $folder, string $existing) {
    ($this->make)('ui/badge', ['folder' => !$folder])->assertExitCode(ExitCode::OK);

    ($this->make)('ui/badge', ['folder' => $folder])
        ->assertExitCode(ExitCode::CANTCREAT)
        ->assertSee("@ui:badge is already $existing, so nothing was written.\n");

    expect(($this->files)())->toHaveCount(2);
})->with([
    'flat beside a folder' => [false, 'one/ui/badge/badge.twig'],
    'a folder beside flat' => [true, 'one/ui/badge.twig'],
]);

it('writes into root n with --root, which may override an earlier root', function() {
    ($this->make)('ui/badge')->assertExitCode(ExitCode::OK);

    ($this->make)('ui/badge', ['root' => '2'])
        ->assertExitCode(ExitCode::OK)
        ->assertSee("Created two/ui/badge.twig\nCreated two/ui/badge.stories.twig\n");

    expect($this->index->get('@ui:badge')?->root)->toBe("$this->tmp/two");
});

it('refuses a root that is not configured, writing nothing', function(string $root) {
    ($this->make)('ui/badge', ['root' => $root])
        ->assertExitCode(ExitCode::USAGE)
        ->assertSee("There's no root $root. templateDirectories has 2, numbered from 1.\n");

    expect(($this->files)())->toBe([]);
})->with(['0', '3', '-1', 'two', '1.0', '']);

it('does not count the site folder as a root', function() {
    ($this->useIndex)(true);
    expect($this->plugin->getIndex()->roots())->toHaveCount(3);

    ($this->make)('ui/badge', ['root' => '3'])->assertExitCode(ExitCode::USAGE);

    expect(($this->files)())->toBe([]);
});

it('refuses a path that is not two segments of lowercase letters, digits and hyphens', function(string $path) {
    ($this->make)($path)
        ->assertExitCode(ExitCode::USAGE)
        ->assertSee("Give the component as <category>/<name>, each of lowercase letters, digits and hyphens, such as ui/badge.\n");

    expect(($this->files)())->toBe([]);
})->with(['badge', 'ui/badge/x', 'UI/badge', 'ui/Badge', 'ui/bad_ge', 'ui/badge.twig', '../badge', 'ui/..', '/badge', 'ui/', "ui/badge\n", 'ui\\badge', 'ui/ba dge', '']);

it('names the component from its hyphenated name', function() {
    ($this->make)('form/icon-button-2')->assertExitCode(ExitCode::OK);

    expect(file_get_contents("$this->tmp/one/form/icon-button-2.twig"))->toContain("name: 'Icon button 2',")
        ->toContain('<div class="icon-button-2"></div>')
        ->and($this->index->get('@form:icon-button-2')?->name)->toBe('Icon button 2');
});
