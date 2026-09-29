<?php

declare(strict_types=1);

/*
 * Code-only props, the Props tab and icons (task 3.5): TS-17 steps 1-5 and TN-21. Picking an
 * icon in a browser is TS-17 step 6.
 *
 * The sandbox config's `icons` names tests/fixtures/icons (setup.sh), so the viewers and the
 * render offer `arrow`, `close` and `star`.
 */

use craft\helpers\FileHelper;
use craft\helpers\Json;
use Twig\Error\SyntaxError;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\console\controllers\CheckController;
use webdna\componentlibrary\services\Index;
use webdna\componentlibrary\services\Shares;

const ICONS = __DIR__ . '/../fixtures/icons';
const ICON_VIEWER = 'admin/component-library/@ui:icon-button';

beforeEach(function() {
    $this->withExceptionHandling();
    $this->plugin = ComponentLibrary::getInstance();
    $this->original = $this->plugin->getIndex();
    $this->tmp = null;
    $token = $this->plugin->getRenderer()->createToken('user:' . Craft::$app->getUsers()->getUserByUsernameOrEmail('admin')?->id);

    // A preview render of `@ui:icon-button`'s Default story, with request props when given.
    $this->render = function(?array $props = null) use ($token): string {
        $query = array_filter(['token' => $token, 'component' => '@ui:icon-button', 'story' => 'Default', 'props' => $props === null ? null : Json::encode($props)]);

        return $this->get('?' . http_build_query($query))->assertOk()->content;
    };
});

afterEach(function() {
    $this->plugin->set('index', $this->original);
    if ($this->tmp) {
        FileHelper::removeDirectory($this->tmp);
    }
});

/** The CL009 lines of a check over the given index. */
function iconProblems(Index $index): array
{
    $plugin = ComponentLibrary::getInstance();
    $plugin->set('index', $index);
    $problems = (new CheckController('check', $plugin))->problems();

    return array_values(array_filter($problems, fn(array $problem) => $problem['code'] === 'CL009'));
}

/** The viewer's state from a page, as viewer.js reads it. */
function propsConfig(string $html): array
{
    return Json::decode(html_entity_decode((string)preg_replace('/.*data-cl-viewer="([^"]*)".*/s', '$1', $html)));
}

describe('BR-44 icon names', function() {
    it('lists each svg and twig basename once, sorted, skipping _ files, stories and others', function() {
        expect((new Index(['icons' => FileHelper::normalizePath(ICONS)]))->iconNames())->toBe(['arrow', 'close', 'star']);
    });

    it('has none when icons is unset, missing or empty', function() {
        $empty = FileHelper::normalizePath(Craft::$app->getPath()->getTempPath() . '/cl-icons-' . uniqid());
        FileHelper::createDirectory($empty);
        $this->tmp = $empty;

        expect((new Index())->iconNames())->toBe([])
            ->and((new Index(['icons' => ICONS . '/nope']))->iconNames())->toBe([])
            ->and((new Index(['icons' => $empty]))->iconNames())->toBe([]);
    });

    it('reads the icons setting from the config, alias and all', function() {
        expect($this->original->iconNames())->toBe(['arrow', 'close', 'star']);
    });
});

describe('BR-5 control and icon in the tag', function() {
    it('reads control and the icon type', function() {
        $component = parseTag(file_get_contents(FIXTURES . '/ui/icon-button.twig'), 'ui/icon-button.twig');

        expect($component->props['icon']->type)->toBe('icon')
            ->and($component->props['icon']->default)->toBe('arrow')
            ->and($component->props['icon']->control)->toBeTrue()
            ->and($component->props['label']->control)->toBeTrue()
            ->and($component->props['href']->control)->toBeFalse()
            ->and($component->props['type']->control)->toBeFalse();
    });

    it('refuses a control that is not true or false, and an icon default that is not a name', function(string $props, string $message) {
        expect(fn() => parseTag("{% component { props: $props } %}"))->toThrow(SyntaxError::class, $message);
    })->with([
        'control string' => ["{ x: { type: 'string', control: 'no' } }", '"props.x.control" must be true or false'],
        'icon number' => ["{ x: { type: 'icon', default: 3 } }", '"props.x.default" does not match type icon'],
        'icon options' => ["{ x: { type: 'icon', options: ['a'] } }", '"props.x.options" applies to the select type only'],
    ]);
});

describe('BR-24 and TN-21 request props', function() {
    it('renders the story’s values for code-only props, and a listed icon', function() {
        $html = ($this->render)(['href' => 'javascript:alert(1)', 'type' => 'reset', 'icon' => 'star']);

        expect($html)->toContain('<button type="button">')
            ->and($html)->toContain('data-icon="star"')
            ->and($html)->not->toContain('javascript')
            ->and($html)->not->toContain('reset');
    });

    it('keeps the story’s icon for one that is not listed', function(mixed $icon) {
        $html = ($this->render)(['icon' => $icon]);

        expect($html)->toContain('data-icon="arrow"')
            ->and($html)->not->toContain('../')
            ->and($html)->not->toContain('<b>');
    })->with(['path' => '../../x', 'markup' => '<b>', 'array' => [['star']], 'case' => 'Star', 'svg' => 'star.svg']);

    it('leaves code-only props out of the viewer’s settings', function() {
        $html = $this->actingAs('admin')->get(ICON_VIEWER . '?' . http_build_query(['props' => Json::encode(['href' => 'javascript:alert(1)', 'label' => 'Hi'])]))->assertOk()->content;

        expect(propsConfig($html)['overrides'])->toBe(['label' => 'Hi'])
            ->and($html)->not->toContain('alert(1)');
    });
});

describe('BR-43 the viewers', function() {
    it('has controls for label and icon only, with the icon names to pick from', function() {
        $html = $this->actingAs('admin')->get(ICON_VIEWER)->assertOk()->content;

        expect($html)->toContain('data-cl-prop="label"')
            ->and($html)->toContain('data-cl-prop="icon"')
            ->and($html)->not->toContain('data-cl-prop="href"')
            ->and($html)->not->toContain('data-cl-prop="type"')
            ->and(preg_match('/<select[^>]*name="icon"[^>]*>(.*?)<\/select>/s', $html, $select))->toBe(1)
            ->and(preg_match_all('/<option value="([^"]*)"/', $select[1], $options))->toBeGreaterThan(0)
            ->and($options[1])->toBe(['', 'arrow', 'close', 'star'])
            ->and($select[1])->toMatch('/<option value="arrow" selected/')
            ->and(array_keys(propsConfig($html)['types']))->toBe(['label', 'icon']);
    });

    it('lists every prop in the Props tab, in order, marked control or code only', function() {
        $html = $this->actingAs('admin')->get(ICON_VIEWER)->assertOk()->content;

        expect($html)->toContain('id="tab-cl-props"')
            ->and(strpos($html, 'id="tab-cl-settings"'))->toBeLessThan((int)strpos($html, 'id="tab-cl-props"'))
            ->and($html)->toContain('data-cl-props')
            ->and(preg_match_all('/data-cl-prop-row="(\w+)" data-cl-control="(true|false)"/', $html, $rows))->toBe(4)
            ->and(array_combine($rows[1], $rows[2]))->toBe(['label' => 'true', 'icon' => 'true', 'href' => 'false', 'type' => 'false'])
            ->and($html)->toContain('If set, renders an &lt;a&gt;.')
            ->and($html)->toContain('Code only')
            ->and($html)->toContain('3 icons');
    });

    it('says so for a component with no props', function() {
        $html = $this->actingAs('admin')->get('admin/component-library/@ui:throws')->assertOk()->content;

        expect($html)->toContain('This component has no props.');
    });

    it('shows the same Props tab on a share link', function() {
        $form = webdna\componentlibrary\models\ShareForm::blank();
        $form->label = 'Acme';
        $token = $this->plugin->getShares()->create($form, (int)Craft::$app->getUsers()->getUserByUsernameOrEmail('admin')?->id);
        $html = $this->get(Shares::URL_PATH . "$token/@ui:icon-button")->assertOk()->content;

        expect(preg_match_all('/data-cl-prop-row="(\w+)" data-cl-control="(true|false)"/', $html, $rows))->toBe(4)
            ->and($html)->not->toContain('data-cl-prop="href"');
    });
});

describe('BR-31 CL009', function() {
    it('warns about an icon prop when there are no icons', function() {
        $problems = iconProblems(new Index(['templateDirectories' => [FileHelper::normalizePath(FIXTURES)], 'sites' => FIXTURES . '/_sites', 'legacy' => true]));

        expect($problems)->toHaveCount(1)
            ->and($problems[0]['path'])->toEndWith('ui/icon-button.twig')
            ->and($problems[0]['message'])->toContain('Prop icon is an icon, but there are no icons to pick from')
            ->and(CheckController::ERRORS)->not->toContain('CL009');
    });

    it('warns about an icon default that is not a listed name', function() {
        $this->tmp = FileHelper::normalizePath(Craft::$app->getPath()->getTempPath() . '/cl-icons-' . uniqid());
        FileHelper::writeToFile("$this->tmp/ui/odd.twig", "{% component { props: { icon: { type: 'icon', default: 'nope' } } } %}\n");

        $problems = iconProblems(new Index(['templateDirectories' => [$this->tmp], 'icons' => FileHelper::normalizePath(ICONS)]));

        expect($problems)->toHaveCount(1)
            ->and($problems[0]['message'])->toContain('"nope"');
    });

    it('is quiet when the icons are there and the default is one of them', function() {
        expect(iconProblems(new Index(['templateDirectories' => [FileHelper::normalizePath(FIXTURES)], 'sites' => FIXTURES . '/_sites', 'legacy' => true, 'icons' => FileHelper::normalizePath(ICONS)])))->toBe([]);
    });
});
