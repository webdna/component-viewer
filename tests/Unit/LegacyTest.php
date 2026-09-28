<?php

declare(strict_types=1);

/*
 * The legacy adapter (BR-15, BR-16, BR-10's variants, BR-12 b): v1 `.config.json` files, read
 * through the index.
 *
 * `legacy/button` is clean, so it sits in the sandbox config's root. The failures and warnings
 * (TN-9, placeholders, a tag beside a config) sit in tests/fixtures/edge, outside that config,
 * so the clean fixture config stays clean.
 */

use craft\events\TemplateEvent;
use craft\helpers\FileHelper;
use craft\web\View;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\legacy\ConfigJsonAdapter;
use webdna\componentlibrary\services\Index;
use yii\base\Event;

const LEGACY_EDGE = __DIR__ . '/../fixtures/edge';

function legacyEdgeIndex(): Index
{
    $index = new Index(['templateDirectories' => [FileHelper::normalizePath(LEGACY_EDGE)]]);
    $index->invalidate();

    return $index;
}

beforeEach(function() {
    $this->index = ComponentLibrary::getInstance()->getIndex();
    $this->index->invalidate();
    $this->index->reset();
    $this->templates = Craft::getAlias('@templates');

    // Every config render, with the template mode it ran in.
    $this->renders = [];
    $this->listener = function(TemplateEvent $event) {
        if (str_ends_with($event->template, ConfigJsonAdapter::SUFFIX)) {
            $this->renders[] = [$event->template, $event->templateMode];
        }
    };
    Event::on(View::class, View::EVENT_BEFORE_RENDER_TEMPLATE, $this->listener);
});

afterEach(function() {
    Event::off(View::class, View::EVENT_BEFORE_RENDER_TEMPLATE, $this->listener);
    Craft::setAlias('@templates', $this->templates);
    Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_SITE);
});

describe('reading a config (BR-15)', function() {
    it('renders the config as Twig and reads its keys, with the legacy handle over the derived one', function() {
        $button = $this->index->get('@ui:legacy-button');
        $root = FileHelper::normalizePath(FIXTURES);

        expect($button)->not->toBeNull()
            ->and($this->index->get('@legacy:button'))->toBeNull()
            ->and($button->name)->toBe('Legacy button')
            ->and($button->status)->toBe('ready')
            ->and($button->viewClass)->toBe('p-4')
            ->and($button->path)->toBe("$root/legacy/button/button.twig")
            ->and($button->configPath)->toBe("$root/legacy/button/button.config.json")
            ->and($button->errors)->toBe([])
            ->and($button->warnings)->toBe([])
            ->and($button->props['body']->default)->toBe('<strong>Rendered</strong> by Twig');
    });

    it('maps v1 variables to prop types, with defaults from the context', function() {
        $props = $this->index->get('@ui:legacy-button')->props;

        expect(array_map(fn($prop) => $prop->type, $props))->toBe([
            'label' => 'string',
            'body' => 'text',
            'count' => 'number',
            'disabled' => 'bool',
            'style' => 'select',
            'colour' => 'string',
            'icon' => 'string',
        ])
            ->and($props['style']->options)->toBe(['solid' => 'Solid', 'outline' => 'Outline'])
            ->and($props['style']->default)->toBe('outline')
            ->and($props['count']->default)->toBe(3)
            ->and($props['disabled']->default)->toBeFalse()
            // Context without a variable is passed to the component, but isn't a prop.
            ->and($props)->not->toHaveKey('href');
    });

    it('turns a select without options into a string prop', function() {
        expect(legacyEdgeIndex()->get('@legacy:placeholders')->props['image']->type)->toBe('string');
    });

    it('takes a sibling readme.md as the notes, unrendered', function() {
        expect($this->index->get('@ui:legacy-button')->notes)
            ->toBe("# Legacy button\n\nThe v1 notes for this button, kept as **Markdown**.");
    });

    it('renders in site mode with no variables, even from a control panel request', function() {
        Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_CP);
        $cpPath = Craft::$app->getView()->getTemplatesPath();

        $button = $this->index->get('@ui:legacy-button');

        expect($button->errors)->toBe([])
            ->and($this->renders)->toBe([['legacy/button/button.config.json', View::TEMPLATE_MODE_SITE]])
            ->and(Craft::$app->getView()->getTemplateMode())->toBe(View::TEMPLATE_MODE_CP)
            ->and(Craft::$app->getView()->getTemplatesPath())->toBe($cpPath);
    });

    it('switches to site mode itself when called outside an index build', function() {
        Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_CP);

        $button = ConfigJsonAdapter::read(FileHelper::normalizePath(FIXTURES), 'legacy/button/button.config.json');

        expect($button->name)->toBe('Legacy button')
            ->and($this->renders)->toBe([['legacy/button/button.config.json', View::TEMPLATE_MODE_SITE]])
            ->and(Craft::$app->getView()->getTemplateMode())->toBe(View::TEMPLATE_MODE_CP);
    });

    it('renders a config under @templates by its name there, so its site-root includes resolve', function() {
        Craft::setAlias('@templates', FileHelper::normalizePath(dirname(LEGACY_EDGE)));
        $sitePath = legacyEdgeIndex()->get('@legacy:site-path');

        expect($sitePath->errors)->toBe([])
            ->and($sitePath->name)->toBe('Named through a site-root include')
            ->and(array_column($this->renders, 0))->toContain('edge/legacy/site-path.config.json');
    });

    it('ignores a config without a sibling template', function() {
        expect(legacyEdgeIndex()->all())->not->toHaveKey('@legacy:orphan');
    });

    it('reports a legacy handle templates cannot include, and derives one instead', function() {
        $placeholders = legacyEdgeIndex()->get('@legacy:placeholders');

        expect($placeholders->errors)->toHaveCount(1)
            ->and($placeholders->errors[0]['code'])->toBe('CL005')
            ->and($placeholders->errors[0]['message'])->toContain('@placeholders');
    });
});

describe('variants (BR-10)', function() {
    it('makes each variant a story, its context over the base context', function() {
        $stories = $this->index->get('@ui:legacy-button')->stories;

        expect(array_keys($stories))->toBe(['Solid', 'Sold out'])
            ->and($stories['Solid']->props['style'])->toBe('solid')
            ->and($stories['Solid']->props['label'])->toBe('Buy now')
            ->and($stories['Solid']->props['href'])->toBe('#')
            ->and($stories['Sold out']->props['disabled'])->toBeTrue()
            ->and($stories['Sold out']->props['label'])->toBe('Sold out')
            ->and($stories['Sold out']->block)->toBeNull();
    });

    it('names unnamed variants and keeps duplicate names apart', function() {
        expect(array_keys(legacyEdgeIndex()->get('@legacy:placeholders')->stories))
            ->toBe(['Variant 1', 'Variant 2', 'Twin', 'Twin (2)']);
    });

    it('gives a config without variants one Default story holding its whole context', function() {
        Craft::setAlias('@templates', FileHelper::normalizePath(dirname(LEGACY_EDGE)));
        $sitePath = legacyEdgeIndex()->get('@legacy:site-path');

        expect(array_keys($sitePath->stories))->toBe(['Default'])
            ->and($sitePath->stories['Default']->props)->toBe(['tone' => 'plain', 'size' => 2])
            ->and($sitePath->props)->toBe([]);
    });
});

describe('placeholders (BR-16)', function() {
    it('keeps {include:@handle} as written, for the preview to render', function() {
        $button = $this->index->get('@ui:legacy-button');

        expect($button->props['icon']->default)->toBe('{include:@ui:good}')
            ->and(preg_match(ConfigJsonAdapter::INCLUDE_PATTERN, $button->props['icon']->default, $match))->toBe(1)
            ->and($match[1])->toBe('@ui:good');
    });

    it('leaves {ref:}, {entry:} and {asset:} literal and reports each one once, at its line', function() {
        $placeholders = legacyEdgeIndex()->get('@legacy:placeholders');

        expect($placeholders->stories['Variant 1']->props)->toMatchArray([
            'title' => '{ref:entry:1:title}',
            'teaser' => 'Read {entry:2:title} next',
            'image' => '{asset:3}',
        ])
            ->and(array_map(fn(array $warning) => [$warning['code'], $warning['line'], strtok($warning['message'], ' ')], $placeholders->warnings))
            ->toBe([
                ['CL006', 5, '{ref:entry:1:title}'],
                ['CL006', 6, '{entry:2:title}'],
                ['CL006', 7, '{asset:3}'],
            ]);
    });
});

describe('a config that fails (TN-9)', function() {
    it('lists the component with the render error and its line, and indexes the rest', function() {
        $index = legacyEdgeIndex();
        $broken = $index->get('@legacy:broken');

        expect($broken)->not->toBeNull()
            ->and($broken->path)->toEndWith('/legacy/broken.twig')
            ->and($broken->name)->toBeNull()
            ->and($broken->errors)->toHaveCount(1)
            ->and($broken->errors[0]['code'])->toBe('CL005')
            ->and($broken->errors[0]['path'])->toEndWith('/legacy/broken.config.json')
            ->and($broken->errors[0]['line'])->toBe(3)
            ->and(array_keys($broken->stories))->toBe(['Default'])
            ->and($index->all())->toHaveKeys(['@legacy:placeholders', '@cards:card']);
    });

    it('lists a config that renders something other than JSON', function() {
        $notJson = legacyEdgeIndex()->get('@legacy:not-json');

        expect($notJson->errors)->toHaveCount(1)
            ->and($notJson->errors[0]['code'])->toBe('CL005')
            ->and($notJson->errors[0]['message'])->toContain('valid JSON');
    });
});

describe('a tag beside a config (BR-12)', function() {
    it('uses the tag, keeps the config handle while the tag has none, and warns', function() {
        $converted = legacyEdgeIndex()->get('@legacy:was-converted');

        expect($converted)->not->toBeNull()
            ->and($converted->name)->toBe('Converted')
            ->and($converted->status)->toBe('ready')
            ->and($converted->configPath)->toBeNull()
            ->and($converted->errors)->toBe([])
            ->and($converted->warnings)->toHaveCount(1)
            ->and($converted->warnings[0]['code'])->toBe('CL007')
            ->and($converted->warnings[0]['path'])->toEndWith('/legacy/converted.config.json');
    });
});

describe('cost (BR-33)', function() {
    it('renders configs only while building, never on a warm lookup', function() {
        $this->index->all();
        expect($this->renders)->toHaveCount(1);

        $this->renders = [];
        $this->index->reset();
        foreach (range(1, 50) as $i) {
            $this->index->get('@ui:legacy-button');
        }

        expect($this->index->builds)->toBe(0)
            ->and($this->renders)->toBe([]);
    });
});
