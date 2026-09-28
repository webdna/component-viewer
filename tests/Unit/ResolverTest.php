<?php

declare(strict_types=1);

/*
 * The resolver (BR-19, TN-17): a relative path across the roots, highest precedence first, for
 * consumers like mw-core's Handlebars service. It runs on the plugin's own index, which reads the
 * sandbox fixture config (the fixture templates, with site versions in `_sites`).
 */

use craft\helpers\FileHelper;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\services\Index;
use webdna\componentlibrary\services\Resolver;

function fixture(string $path): string
{
    return FileHelper::normalizePath(FIXTURES . "/$path");
}

beforeEach(function() {
    $this->resolver = ComponentLibrary::getInstance()->getResolver();
});

afterEach(function() {
    Craft::$app->getSites()->setCurrentSite(Craft::$app->getSites()->getPrimarySite());
});

describe('precedence', function() {
    it('is the plugin resolver, over the plugin index', function() {
        expect($this->resolver)->toBeInstanceOf(Resolver::class)
            ->and(ComponentLibrary::getInstance()->resolver)->toBe($this->resolver);
    });

    it('returns the site version for the site that has one', function() {
        expect($this->resolver->resolve('ui/good.twig', 'second'))->toBe(fixture('_sites/second/ui/good.twig'))
            ->and($this->resolver->resolve('ui/good.twig', 'default'))->toBe(fixture('ui/good.twig'));
    });

    it('falls back to the base file where the site has no version', function() {
        expect($this->resolver->resolve('ui/nested.twig', 'second'))->toBe(fixture('ui/nested.twig'));
    });

    it('uses the current site when no handle is given', function() {
        expect($this->resolver->resolve('ui/good.twig'))->toBe(fixture('ui/good.twig'));

        Craft::$app->getSites()->setCurrentSite('second');

        expect($this->resolver->resolve('ui/good.twig'))->toBe(fixture('_sites/second/ui/good.twig'));
    });

    it('lets a later root beat an earlier one', function(array $dirs, string $expected) {
        $resolver = new Resolver(['index' => new Index(['templateDirectories' => array_map('fixture', $dirs)])]);

        expect($resolver->resolve('ui/good.twig'))->toBe(fixture($expected));
    })->with([
        'site folder last' => [['.', '_sites/second'], '_sites/second/ui/good.twig'],
        'site folder first' => [['_sites/second', '.'], 'ui/good.twig'],
    ]);

    it('ignores the site handle when site versions are off', function() {
        $resolver = new Resolver(['index' => new Index(['templateDirectories' => [fixture('.')]])]);

        expect($resolver->resolve('ui/good.twig', 'second'))->toBe(fixture('ui/good.twig'));
    });

    it('resolves any file in a root, not only components', function() {
        expect($this->resolver->resolve('legacy/button/readme.md'))->toBe(fixture('legacy/button/readme.md'));
    });
});

describe('null', function() {
    it('returns null for a file no root has', function(string $path) {
        expect($this->resolver->resolve($path))->toBeNull();
    })->with(['ui/nope.twig', 'ui', '', 'ui/']);

    it('returns null for a file only another root set has', function() {
        $edge = FileHelper::normalizePath(dirname(FIXTURES) . '/edge');
        $resolver = new Resolver(['index' => new Index(['templateDirectories' => [$edge]])]);

        expect($resolver->resolve('ui/good.twig'))->toBeNull()
            ->and($resolver->resolve('loose.twig'))->toBe("$edge/loose.twig");
    });

    // TN-17. Each of these names a real file, so only the refusal makes them null.
    it('refuses a path that climbs or is absolute', function(string $path) {
        expect($this->resolver->resolve($path))->toBeNull();
    })->with([
        'TN-17 climb' => '../config/db.php',
        'TN-17 absolute' => '/etc/passwd',
        'climb and return' => 'ui/../ui/good.twig',
        'climb from a site' => '../../../../../../../../etc/passwd',
        'rooted relative' => '/ui/good.twig',
        'absolute into a root' => fixture('ui/good.twig'),
        'null byte' => "ui/good.twig\0.hbs",
    ]);

    it('refuses a site handle Craft does not know', function(string $handle) {
        expect($this->resolver->resolve('ui/good.twig', $handle))->toBeNull();
    })->with([
        'unknown' => 'nope',
        'traversal' => '../second',
        'traversal to the base' => '..',
        'empty' => '',
    ]);

    it('never reaches another site through the sites folder', function() {
        expect($this->resolver->resolve('_sites/second/ui/good.twig', 'default'))->toBeNull()
            ->and($this->resolver->resolve('_sites/second/ui/good.twig', 'second'))->toBeNull();
    });
});
