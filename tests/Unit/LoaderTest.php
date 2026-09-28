<?php

declare(strict_types=1);

/*
 * The loader (BR-17, BR-18): handles resolve through the index, in site and CP modes, and every
 * other name reaches Craft's loader untouched.
 *
 * Templates render through Craft's own environments. Page-level sources are inline test inputs;
 * the components they include are the fixture files, found by handle.
 */

use craft\events\RegisterTemplateRootsEvent;
use craft\helpers\FileHelper;
use craft\web\twig\TemplateLoader;
use craft\web\twig\TemplateLoaderException;
use craft\web\View;
use Twig\Loader\LoaderInterface;
use Twig\Source;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\services\Index;
use webdna\componentlibrary\twig\Loader;
use yii\base\Event;

const NAMESPACE_ROOT = __DIR__ . '/../fixtures/namespace';

function renderIn(string $mode, string $source): string
{
    return twigIn($mode)->createTemplate($source)->render();
}

/** The wrapper Craft's environment holds for a mode. */
function loaderIn(string $mode): Loader
{
    $loader = twigIn($mode)->getLoader();
    assert($loader instanceof Loader);

    return $loader;
}

/**
 * A loader that records every name it's asked about, and answers as Craft's does.
 */
final class SpyLoader implements LoaderInterface
{
    /** @var list<string> */
    public array $names = [];

    public function __construct(private LoaderInterface $craft)
    {
    }

    public function getSourceContext(string $name): Source
    {
        $this->names[] = $name;
        return $this->craft->getSourceContext($name);
    }

    public function getCacheKey(string $name): string
    {
        $this->names[] = $name;
        return $this->craft->getCacheKey($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        $this->names[] = $name;
        return $this->craft->isFresh($name, $time);
    }

    public function exists(string $name): bool
    {
        $this->names[] = $name;
        return $this->craft->exists($name);
    }
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
    $view = Craft::$app->getView();
    $view->setTemplateMode(View::TEMPLATE_MODE_CP);
    $view->setTemplateMode(View::TEMPLATE_MODE_SITE);
});

describe('registration', function() {
    it('wraps the loader Craft created, in each template mode', function(string $mode) {
        expect(twigIn($mode)->getLoader())->toBeInstanceOf(Loader::class)
            ->and(loaderIn($mode)->inner)->toBeInstanceOf(TemplateLoader::class);
    })->with([View::TEMPLATE_MODE_SITE, View::TEMPLATE_MODE_CP]);

    it('wraps the environments of any other view too, once', function() {
        /** @var Loader $loader */
        $loader = (new View())->getTwig()->getLoader();

        expect($loader)->toBeInstanceOf(Loader::class)
            ->and($loader->inner)->toBeInstanceOf(TemplateLoader::class);
    });
});

describe('owned names (BR-17)', function() {
    it('owns component handles only', function(string $name, bool $owned) {
        expect(Loader::owns($name))->toBe($owned);
    })->with([
        ['@ui:good', true],
        ['@form:fields:text', true],
        ['@ui:button--primary', true],
        ['@blocks:text_block', true],
        ['@ui', false],
        ['ui:good', false],
        ['@ns/file.twig', false],
        ['@ui:good/extra', false],
        ['@ui:good.twig', false],
        ['_components/ui/button.twig', false],
        ['_sites/default/head.twig', false],
        ['@ui:', false],
        ["@ui:good\n", false],
    ]);

    it('never hands an owned name to Craft, and hands it every other name unchanged', function() {
        $view = Craft::$app->getView();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        $view->setTemplatesPath(FileHelper::normalizePath(FIXTURES));
        $spy = new SpyLoader(new TemplateLoader($view));
        $loader = new Loader($spy);

        $loader->exists('@ui:good');
        $loader->getCacheKey('@ui:good');
        $loader->getSourceContext('@ui:good');
        $loader->isFresh('@ui:good', time());
        $loader->exists('@ui:nope');
        $loader->exists('ui/good.twig');
        $loader->getCacheKey('ui/good.twig');

        expect($spy->names)->toBe(['ui/good.twig', 'ui/good.twig']);
    });
});

describe('resolving handles (BR-17)', function() {
    it('resolves a handle to its file through the index', function() {
        $loader = twigIn(View::TEMPLATE_MODE_SITE)->getLoader();
        $path = $this->index->get('@ui:good')->path;
        $source = $loader->getSourceContext('@ui:good');

        expect($loader->exists('@ui:good'))->toBeTrue()
            ->and($loader->getCacheKey('@ui:good'))->toBe($path)
            ->and($source->getName())->toBe('@ui:good')
            ->and($source->getPath())->toBe($path)
            ->and($source->getCode())->toBe(file_get_contents($path))
            ->and($loader->isFresh('@ui:good', filemtime($path)))->toBeTrue()
            ->and($loader->isFresh('@ui:good', filemtime($path) - 1))->toBeFalse();
    });

    it('resolves a handle in every way a template names another', function(string $mode, string $source, array $expected) {
        $html = renderIn($mode, $source);

        foreach ($expected as $fragment) {
            expect($html)->toContain($fragment);
        }
    })->with([View::TEMPLATE_MODE_SITE, View::TEMPLATE_MODE_CP])->with([
        'include' => [
            "{% include '@ui:good' with { label: 'Tagged' } only %}",
            ['btn--primary">Tagged</button>'],
        ],
        'include()' => [
            "{{ include('@ui:good', { label: 'Called' }) }}",
            ['btn--primary">Called</button>'],
        ],
        'embed' => [
            "{% embed '@ui:nested' %}{% block actions %}<b>Mine</b>{% endblock %}{% endembed %}",
            ['class="dialog"', 'Are you sure?', '<b>Mine</b>'],
        ],
        'extends' => [
            "{% extends '@ui:nested' %}{% block panel %}<i>Over</i>{% endblock %}",
            ['class="dialog"', '<i>Over</i>'],
        ],
        'source()' => [
            "{{ source('@ui:good') }}",
            ['{% component {', 'Good button'],
        ],
        'a handle reaching the loader at runtime' => [
            "{% set type = 'good' %}{% include '@ui:' ~ type %}",
            ['btn--primary">Save</button>'],
        ],
    ]);

    it('resolves a handle to the current site\'s version', function() {
        $source = "{% include '@ui:good' with { label: 'Mine' } only %}";

        expect(renderIn(View::TEMPLATE_MODE_SITE, $source))->toContain('btn--primary">Mine</button>');

        Craft::$app->getSites()->setCurrentSite('second');

        expect(renderIn(View::TEMPLATE_MODE_SITE, $source))->toContain('<button type="button" class="second">Mine</button>');
    });

    it('renders the stories fixtures through Craft, their handles included', function() {
        $view = Craft::$app->getView();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        $view->setTemplatesPath(FileHelper::normalizePath(FIXTURES));
        $twig = $view->getTwig();

        $toolbar = $this->index->get('@ui:good')->stories['In a toolbar']
            ->render($twig, '@ui:good', 'ui/good.stories.twig', ['label' => 'Undo']);
        $panel = $this->index->get('@ui:nested')->stories['Custom panel']
            ->render($twig, '@ui:nested', 'ui/nested.stories.twig', ['id' => 'invite', 'open' => true, 'title' => 'Invite somebody']);

        expect($toolbar)->toMatch('#<div class="toolbar"><button class="btn btn--primary">Undo</button>\s*</div>#')
            ->and($panel)->toContain('data-test="custom-panel"')
            ->and($panel)->toContain('<h2 id="invite-title">Invite somebody</h2>')
            ->and($panel)->toContain('btn--secondary">Send invite</button>')
            ->and($panel)->not->toContain('Are you sure?');
    });
});

describe('unknown handles (BR-17, TN-5)', function() {
    it('renders nothing for an ignore-missing include of an unknown handle', function(string $mode, string $source) {
        expect(trim(renderIn($mode, $source)))->toBe('');
    })->with([View::TEMPLATE_MODE_SITE, View::TEMPLATE_MODE_CP])->with([
        'include' => "{% include '@ui:nope' ignore missing %}",
        'include()' => "{{ include('@ui:nope', ignore_missing = true) }}",
        'source()' => "{{ source('@ui:nope', ignore_missing = true) }}",
        'a variant handle' => "{% include '@ui:good--primary' ignore missing %}",
        'a fallback list' => "{% include ['@ui:nope', '@ui:missing'] ignore missing %}",
    ]);

    it('falls back along a list to the first handle that exists', function() {
        expect(renderIn(View::TEMPLATE_MODE_SITE, "{% include ['@ui:nope', '@ui:good'] %}"))->toContain('btn--primary">Save</button>');
    });

    it('raises Craft\'s missing-template error for an unknown handle', function(string $mode) {
        // Twig drops the final full stop when it appends the location.
        expect(fn() => renderIn($mode, "{% include '@ui:nope' %}"))
            ->toThrow(TemplateLoaderException::class, 'Unable to find the template “@ui:nope” in "__string_template__');

        $loader = twigIn($mode)->getLoader();
        expect($loader->exists('@ui:nope'))->toBeFalse()
            ->and(fn() => $loader->getCacheKey('@ui:nope'))->toThrow(TemplateLoaderException::class);
    })->with([View::TEMPLATE_MODE_SITE, View::TEMPLATE_MODE_CP]);
});

describe('names Craft resolves (BR-17, BR-18, TN-6)', function() {
    it('resolves namespaces and paths exactly as Craft does without the plugin', function(string $name) {
        $handler = function(RegisterTemplateRootsEvent $event): void {
            $event->roots['@clns'] = FileHelper::normalizePath(NAMESPACE_ROOT);
        };
        Event::on(View::class, View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS, $handler);

        try {
            // A new view, since Craft caches the template roots per view.
            $view = new View();
            $view->setTemplateMode(View::TEMPLATE_MODE_SITE);
            $view->setTemplatesPath(FileHelper::normalizePath(FIXTURES));
            $loader = $view->getTwig()->getLoader();
            $craft = new TemplateLoader($view);

            expect($loader)->toBeInstanceOf(Loader::class)
                ->and($loader->exists($name))->toBe($craft->exists($name))->toBeTrue()
                ->and($loader->getCacheKey($name))->toBe($craft->getCacheKey($name))
                ->and($loader->getSourceContext($name))->toEqual($craft->getSourceContext($name));
        } finally {
            Event::off(View::class, View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS, $handler);
        }
    })->with(['@clns/file.twig', '@clns/file', 'ui/good.twig', 'ui/good', 'legacy/button/button.twig']);

    it('raises Craft\'s own error for a missing path', function() {
        $view = Craft::$app->getView();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        $loader = $view->getTwig()->getLoader();
        $craft = new TemplateLoader($view);

        expect($loader->exists('_components/nope.twig'))->toBeFalse()
            ->and(fn() => $loader->getCacheKey('_components/nope.twig'))->toThrow(TemplateLoaderException::class, 'Unable to find the template “_components/nope.twig”.')
            ->and(fn() => $craft->getCacheKey('_components/nope.twig'))->toThrow(TemplateLoaderException::class, 'Unable to find the template “_components/nope.twig”.')
            ->and(trim(renderIn(View::TEMPLATE_MODE_SITE, "{% include '_sites/' ~ currentSite.handle ~ '/head.twig' ignore missing %}")))->toBe('');
    });

    it('renders a component included by path the same as by handle, and the index knows the file', function() {
        $view = Craft::$app->getView();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        $view->setTemplatesPath(FileHelper::normalizePath(FIXTURES));

        $byPath = renderIn(View::TEMPLATE_MODE_SITE, "{% include 'ui/good.twig' with { label: 'Same' } only %}");
        $byHandle = renderIn(View::TEMPLATE_MODE_SITE, "{% include '@ui:good' with { label: 'Same' } only %}");

        expect($byPath)->toContain('btn--primary">Same</button>')
            ->and($byPath)->toBe($byHandle)
            ->and($view->resolveTemplate('ui/good.twig'))->toBe($this->index->get('@ui:good')->path);
    });

    it('leaves CP templates resolving', function() {
        expect(twigIn(View::TEMPLATE_MODE_CP)->getLoader()->exists('_layouts/cp'))->toBeTrue()
            ->and(Craft::$app->getView()->renderTemplate('_includes/forms/text', ['name' => 'q'], View::TEMPLATE_MODE_CP))
            ->toContain('name="q"');
    });
});

describe('index use (TS-13, BR-14, BR-33)', function() {
    it('builds the index once for a page of 50 includes, and not at all when warm', function() {
        $root = sys_get_temp_dir() . '/cl-loader-' . uniqid();
        FileHelper::createDirectory("$root/ui");
        FileHelper::createDirectory("$root/pages");
        copy(FIXTURES . '/ui/good.twig', "$root/ui/good.twig");
        copy(FIXTURES . '/pages/fifty.twig', "$root/pages/fifty.twig");

        $plugin = ComponentLibrary::getInstance();
        $index = new Index(['templateDirectories' => [$root]]);
        $index->invalidate();
        $plugin->set('index', $index);

        $view = Craft::$app->getView();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        $view->setTemplatesPath($root);
        $render = function() use ($view, $index): void {
            $index->reset();
            expect(substr_count($view->renderTemplate('pages/fifty'), '<button class="btn btn--primary">'))->toBe(50);
        };

        try {
            // 1. Cold: one build for 50 includes.
            $render();
            expect($index->builds)->toBe(1);

            // 2. A new request with devMode off: no build, no directory iterator.
            Craft::$app->getConfig()->getGeneral()->devMode = false;
            $render();
            expect($index->builds)->toBe(0)->and($index->walks)->toBe(0);

            // With devMode on and nothing changed, one stat walk and no build.
            Craft::$app->getConfig()->getGeneral()->devMode = true;
            $render();
            expect($index->builds)->toBe(0)->and($index->walks)->toBe(1);

            // 3. With devMode on, a touched component rebuilds, once.
            touch("$root/ui/good.twig", time() + 60);
            $render();
            expect($index->builds)->toBe(1)->and($index->walks)->toBe(1);
        } finally {
            $plugin->set('index', $this->index);
            FileHelper::removeDirectory($root);
        }
    });
});
