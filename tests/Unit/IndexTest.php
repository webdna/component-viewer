<?php

declare(strict_types=1);

/*
 * The component index (BR-11 to BR-14, BR-33): roots, scan, handle derivation, precedence, cache.
 *
 * The plugin's own index reads the sandbox config that tests/fixtures/setup.sh writes: the
 * fixture templates, with `_sites` inside them. The edge cases (TN-8's duplicates and friends)
 * sit in a root of their own, tests/fixtures/edge, so the clean fixture config stays clean.
 */

use craft\helpers\FileHelper;
use craft\utilities\ClearCaches;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\services\Index;
use yii\log\Logger;

const EDGE = __DIR__ . '/../fixtures/edge';

function edgeIndex(): Index
{
    $index = new Index(['templateDirectories' => [FileHelper::normalizePath(EDGE)], 'legacy' => true]);
    $index->invalidate();

    return $index;
}

beforeEach(function() {
    $this->index = ComponentLibrary::getInstance()->getIndex();
    $this->index->invalidate();
    $this->index->reset();
    $this->devMode = Craft::$app->getConfig()->getGeneral()->devMode;
});

afterEach(function() {
    Craft::$app->getConfig()->getGeneral()->devMode = $this->devMode;
    Craft::$app->getSites()->setCurrentSite(Craft::$app->getSites()->getPrimarySite());
});

describe('handle derivation (BR-13)', function() {
    it('derives a handle from the path', function(string $path, string $handle) {
        expect(Index::handleFor($path))->toBe($handle);
    })->with([
        ['ui/button.twig', '@ui:button'],
        ['components/button/button.twig', '@components:button'],
        ['form/fields/text.twig', '@form:fields:text'],
        ['ui/button--primary.twig', '@ui:button--primary'],
        ['button/button.twig', '@button'],
        ['button.twig', '@button'],
    ]);
});

describe('roots (BR-11)', function() {
    it('takes its roots from config/component-library.php, with the current site folder last', function() {
        expect($this->index->roots())->toBe([
            FileHelper::normalizePath(FIXTURES),
            FileHelper::normalizePath(FIXTURES) . '/_sites/default',
        ]);
    });

    it('uses the current site, whichever it is', function() {
        Craft::$app->getSites()->setCurrentSite('second');

        expect($this->index->roots())->toBe([
            FileHelper::normalizePath(FIXTURES),
            FileHelper::normalizePath(FIXTURES) . '/_sites/second',
        ]);
    });

    it('defaults to @templates/_components', function() {
        expect((new Index())->roots())->toBe([FileHelper::normalizePath(Craft::getAlias('@templates/_components'))]);
    });

    it('skips a missing root with a warning and indexes the rest (TN-10)', function() {
        $missing = FileHelper::normalizePath(FIXTURES) . '/no-such-root';
        $index = new Index(['templateDirectories' => [$missing, FIXTURES]]);
        $index->invalidate();

        expect($index->all())->toHaveKeys(['@ui:good', '@ui:nested']);

        $warnings = array_filter(
            Craft::getLogger()->messages,
            fn(array $message) => $message[1] === Logger::LEVEL_WARNING && str_contains((string)$message[0], $missing),
        );
        expect($warnings)->toHaveCount(1);
    });
});

describe('scan (BR-12)', function() {
    it('indexes tagged files by handle, in handle order', function() {
        expect(array_keys($this->index->all()))->toBe(['@ui:bad-tag', '@ui:good', '@ui:guest', '@ui:legacy-button', '@ui:nested', '@ui:raw-prop', '@ui:throws']);
    });

    it('never indexes a stories file, a site folder under another name, or an untagged file', function() {
        $handles = array_keys(edgeIndex()->all() + $this->index->all());

        expect($handles)->each->not->toContain('stories')
            ->and($handles)->each->not->toContain('_sites')
            ->and($handles)->not->toContain('@other:commented');
    });

    it('records where a component lives and its stories', function() {
        $good = $this->index->get('@ui:good');
        $root = FileHelper::normalizePath(FIXTURES);

        expect($good->name)->toBe('Good button')
            ->and($good->root)->toBe($root)
            ->and($good->path)->toBe("$root/ui/good.twig")
            ->and($good->storiesPath)->toBe("$root/ui/good.stories.twig")
            ->and(array_keys($good->stories))->toBe(['Default', 'Secondary', 'In a toolbar'])
            ->and($good->errors)->toBe([])
            ->and($good->overrides)->toBeNull();
    });

    it('keeps a component whose tag fails to parse, with the error and its line (TN-7)', function() {
        $bad = $this->index->get('@ui:bad-tag');

        expect($bad->name)->toBeNull()
            ->and($bad->errors)->toHaveCount(1)
            ->and($bad->errors[0]['path'])->toEndWith('/ui/bad-tag.twig')
            ->and($bad->errors[0]['line'])->toBe(3);
    });

    it('falls back to a Default story when the stories file does not parse', function() {
        $broken = edgeIndex()->get('@other:broken');

        expect(array_keys($broken->stories))->toBe(['Default'])
            ->and($broken->stories['Default']->props)->toBe(['label' => 'Hi'])
            ->and($broken->errors)->toHaveCount(1)
            ->and($broken->errors[0]['path'])->toEndWith('/other/broken.stories.twig');
    });

    it('flags a derived handle that templates cannot include', function() {
        $loose = edgeIndex()->get('@loose');

        expect($loose->errors)->toHaveCount(1)
            ->and($loose->errors[0]['message'])->toContain('category folder');
    });
});

describe('precedence (BR-13)', function() {
    it('keeps the first of a duplicated handle by sorted path and lists the rest (TN-8)', function() {
        $card = edgeIndex()->get('@cards:card');
        $root = FileHelper::normalizePath(EDGE);

        expect($card->name)->toBe('Card')
            ->and($card->path)->toBe("$root/cards/card.twig")
            ->and($card->duplicates)->toBe(["$root/cards/card/card.twig", "$root/other/teaser.twig"]);
    });

    it('lets the site folder replace a shared component on its own site only', function() {
        $shared = $this->index->get('@ui:good');

        Craft::$app->getSites()->setCurrentSite('second');
        $second = $this->index->get('@ui:good');

        expect($second->name)->toBe('Good button (second site)')
            ->and($second->path)->toEndWith('/_sites/second/ui/good.twig')
            ->and($second->overrides)->toBe($shared->path)
            ->and(array_keys($second->stories))->toBe(['Default'])
            ->and($this->index->get('@ui:nested')->overrides)->toBeNull();
    });
});

describe('cache (BR-14, BR-33)', function() {
    it('builds once, then serves a warm request without walking a directory (TS-13)', function() {
        $root = sys_get_temp_dir() . '/cl-index-' . uniqid();
        FileHelper::createDirectory("$root/ui");
        copy(FIXTURES . '/ui/good.twig', "$root/ui/good.twig");
        copy(FIXTURES . '/ui/good.stories.twig', "$root/ui/good.stories.twig");

        try {
            $index = new Index(['templateDirectories' => [$root]]);
            $index->invalidate();
            $lookups = fn() => array_map(fn() => $index->get('@ui:good'), range(1, 50));

            // 1. Cold: one build.
            $lookups();
            expect($index->builds)->toBe(1);

            // 2. A new request with devMode off: no build, no directory iterator.
            Craft::$app->getConfig()->getGeneral()->devMode = false;
            $index->reset();
            $lookups();
            expect($index->builds)->toBe(0)->and($index->walks)->toBe(0);

            // With devMode on and nothing changed, one stat walk and no build.
            Craft::$app->getConfig()->getGeneral()->devMode = true;
            $index->reset();
            $lookups();
            expect($index->builds)->toBe(0)->and($index->walks)->toBe(1);

            // 3. With devMode on, a touched file rebuilds, once.
            touch("$root/ui/good.twig", time() + 60);
            $index->reset();
            $lookups();
            expect($index->builds)->toBe(1)->and($index->walks)->toBe(1);
        } finally {
            FileHelper::removeDirectory($root);
        }
    });

    it('keeps one index per site', function() {
        $this->index->all();
        Craft::$app->getSites()->setCurrentSite('second');
        $this->index->all();

        expect($this->index->builds)->toBe(2);
    });

    it('is rebuilt after the Component library index Clear Caches option', function() {
        $this->index->all();

        $option = collect(ClearCaches::cacheOptions())->firstWhere('key', Index::CACHE_TAG);
        expect($option['label'])->toBe('Component library index');

        call_user_func($option['action']);
        $this->index->reset();
        $this->index->all();
        expect($this->index->builds)->toBe(1);
    });

    it('is rebuilt after the data cache is flushed, as clear-caches/all does', function() {
        $this->index->all();

        Craft::$app->getCache()->flush();
        $this->index->reset();
        $this->index->all();
        expect($this->index->builds)->toBe(1);
    });
});
