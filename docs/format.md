# Component format

A component is a Twig file that describes itself with a `{% component %}` tag. Its named
examples, called **stories**, live in a `.stories.twig` file beside it. Both are read by the
library only. The tag renders nothing, so adding one to an existing file can't change a page.

```
templates/_components/
  ui/
    button.twig             @ui:button
    button.stories.twig     its stories
    dialog/
      dialog.twig           @ui:dialog
      dialog.stories.twig
```

`php craft component-library/make ui/button` writes the first pair. `--folder` writes the second
layout.

## The `component` tag

```twig
{% component {
    name: 'Button',
    handle: 'ui:button',
    status: 'ready',
    notes: 'Use **primary** once per screen.',
    viewClass: 'p-4 bg-grey-100',
    props: {
        label: 'Save',
        disabled: false,
        count: 3,
        style: { type: 'select', default: 'primary', options: ['primary', 'secondary'] },
        size: { type: 'select', options: { sm: 'Small', lg: 'Large' }, required: true },
        body: { type: 'text', description: 'Shown under the label' },
    },
} %}
<button class="btn btn--{{ style ?? 'primary' }}"{{ (disabled ?? false) ? ' disabled' }}>
    {{- label ?? 'Save' -}}
</button>
```

The tag can sit anywhere in the file, and a file has at most one. Every key is optional.

| Key | Means |
|---|---|
| `name` | The name shown in the library. Defaults to the handle. |
| `handle` | The short name to include it by, with or without the `@`. Defaults to one derived from the path (see *Handles*). |
| `status` | `prototype`, `wip`, `ready` or `deprecated`. Shown as a badge. |
| `notes` | Markdown, shown on the *Notes* tab. |
| `viewClass` | Classes for the element around the preview: the layout's `viewClass` block. |
| `background` | The preview background it opens on: `site` (the layout's own, the default), `light` or `dark`. Set `dark` for a component made for dark sections, such as a footer. The viewer's *Background* switch changes it for a look. |
| `props` | The settings the component takes (below). |

**Only literal values.** The tag's value must be written out in full: strings, numbers, `true`,
`false`, `null`, lists and hashes. A variable, filter, function call or `~` concatenation is a Twig
syntax error naming the file and line, as is an unknown key, a bad status or background, or a default that
doesn't match its type. The library reads the tag without running the template, and this is what
makes that safe.

**Defaults are for the library only.** On the live site the tag does nothing, so `label: 'Save'`
doesn't set `label`. Keep your template's own `?? 'Save'` fallbacks. That is what lets a component
be converted without retesting every page that uses it.

### Props

A prop is either a **shorthand default**, whose type is inferred from the value, or the **full
form**, a hash with a `type`:

| Shorthand | Type |
|---|---|
| `label: 'Save'`, `icon: null` | `string` |
| `disabled: false` | `bool` |
| `count: 3`, `offset: -1.5` | `number` |
| `attrs: { 'data-x': 1 }`, `items: ['a', 'b']` | `json` |

The full form takes `type`, `default`, `options`, `required`, `description` and `control`. A hash
with any other key, or without `type`, counts as a shorthand `json` default.

Describe each prop once, in the tag. The library's *Props* tab lists every prop with its type,
default, options, description and whether it has a control, so the component needs no Params list
in its doc comment. Give each idea one prop: one `icon`, not a file path prop and a template prop
beside it.

**`control: false` makes a code-only prop.** It's part of the component's API and shows in *Props*,
but gets no control in *Settings*, and the library can't change it: only stories set it. Use it
for anything that doesn't change how the component looks or behaves in a preview, such as a
button's `href` or `type`, and for anything used as script, a link or a path (see below).

```twig
href: { type: 'string', control: false, description: 'If set, renders an <a>.' },
```

| Type | Control in the library | Notes |
|---|---|---|
| `string` | Text field | Up to 2,000 characters. |
| `text` | Text area | Up to 10,000 characters. |
| `bool` | Checkbox | |
| `number` | Number field | |
| `select` | Dropdown | Needs `options`: a list (`['sm', 'lg']`) or a hash of value to label (`{ sm: 'Small' }`). The default must be one of them. Without `required`, the dropdown also offers no value. |
| `json` | Code text area | Any list or hash, nested at most 5 deep. |
| `icon` | Dropdown of icon names | A name from the `icons` folder ([setup](setup.md)), such as `'close'`, never a path. The component turns it into markup itself, e.g. `{% include '_icons/' ~ icon %}` or `svg('@webroot/icons/' ~ icon ~ '.svg')`. The check warns (CL009) if there are no icons, or the default isn't one. |

A value from the library that breaks these limits, or isn't one of a select's options or of the
icon names, is ignored,
and the story's own value (or the default) is used.

**Whatever someone types in the library reaches the component as escaped text.** A `string` or
`text` value of `<b>Hi</b>` shows as those characters, even through `|raw`. HTML in a preview
comes only from files: the template or a story body. The library can't pull in site content by id
either. Only declared props with a control can be changed from the library. Any other value in the
request is dropped.

**Escaping makes a value safe as HTML, and nowhere else.** Some settings aren't shown as text: an
Alpine expression (`open: 'showModal'`), a raw attribute string (`attrs: '@click="…"'`), a link
(`href`), an SVG file path or a template name to include. Anything typed into one of those runs as
script, follows a `javascript:` link, or reads a file. Don't give such a setting a control as a
`string`. Declare it `control: false`, so only the stories' `with` sets it (and only files can
change those), or make it a `select` of fixed values or an `icon`.

## Handles

A component's handle is the tag's `handle`, or else it's derived from the file's path below its
folder: the segments are joined with `:` and the extension is dropped. A file named after its own
folder collapses.

| File | Handle |
|---|---|
| `ui/button.twig` | `@ui:button` |
| `ui/button/button.twig` | `@ui:button` |
| `form/fields/text.twig` | `@form:fields:text` |
| `button.twig` (no folder) | none: give it a `handle`, or move it into a folder |

A handle is `@` followed by at least two parts separated by `:`, each made of letters, digits,
`-` and `_`. Include a component by its handle or by its path, as before:

```twig
{% include '@ui:button' with { label: 'Send' } %}
{% include '_components/ui/button.twig' with { label: 'Send' } %}
{% embed '@ui:dialog' %}{% block panel %}…{% endblock %}{% endembed %}
{{ include('@ui:button', { label: 'Send' }) }}
```

Handles work in `include`, `embed`, `extends`, `include()` and `source()`, on the front end and in
the control panel. An unknown handle is Twig's usual missing-template error, so
`ignore missing` works. Any other name (a path, or a Twig namespace like `@ns/path`) goes to
Craft exactly as it always has.

**Order.** Folders are searched in `templateDirectories` order, then the site folder. When two
folders define one handle, the **later** one wins. That is how a site version replaces a shared
component. Two files claiming one handle in the *same* folder is a mistake. The first by path is
used, and the check reports it.

## Stories

Stories go in `<name>.stories.twig` beside the component:

```twig
{# ui/button.stories.twig #}
{% story 'Default' %}{% endstory %}

{% story 'Secondary' with { label: 'Cancel', style: 'secondary' } %}{% endstory %}

{% story 'In a toolbar' with { label: 'Undo' } %}
<div class="toolbar">{% include '@ui:button' with props only %}</div>
{% endstory %}
```

- **Without a body**, a story renders the component with its prop defaults, overridden by the
  story's `with`.
- **With a body**, the body renders instead. It can be any Twig: several components, surrounding
  markup, or an `embed` filling a component's blocks. The story's settings, with the library's
  current values applied, are in `props`.
- `with` follows the same literal-only rule as the tag. It can also pass values that aren't
  declared props, such as an Alpine expression the component expects.
- Story names are unique within the file. Stories sit at the top level of the file, not inside
  other tags.
- Only the chosen story's body runs.

Filling a component's blocks, as a page would:

```twig
{# ui/dialog.stories.twig #}
{% story 'Confirm' with { open: true, title: 'Delete saved search?' } %}{% endstory %}

{% story 'Custom panel' with { id: 'invite', open: true, title: 'Invite somebody' } %}
{% embed '@ui:dialog' with props only %}
    {% block panel %}
        <h2 id="{{ id }}-title">{{ title }}</h2>
        {% include '@ui:button' with { label: 'Send invite' } only %}
    {% endblock %}
{% endembed %}
{% endstory %}
```

**No stories file** means one story, *Default*, built from the prop defaults. A stories file is
never listed as a component, and only the library's preview renders it.

## Legacy `.config.json` files

A site upgraded from v1 can keep its `<name>.config.json` files by setting `'legacy' => true` in
`config/component-library.php` (see [Upgrading from v1](upgrading-from-v1.md)). Their `variants`
become stories, and a `readme.md` beside them becomes the notes. When a file has both a config and
a `component` tag, the tag wins and the check warns. Delete the config once the tag is in.

## The check

```bash
php craft component-library/check [--strict]
```

It prints one line per problem, `<code> <path>:<line> <message>`, then `<n> problems`. Paths are
relative to the templates folder. It exits 1 on any error, or on any warning with `--strict`, and
otherwise 0.

| Code | Kind | Problem |
|---|---|---|
| CL001 | error | A `component` or `story` tag that doesn't parse, or holds a non-literal value. |
| CL002 | error | Two files in one folder claim the same handle. |
| CL003 | error | An `include`, `embed` or `extends` of a handle that doesn't exist, anywhere under the templates folder. |
| CL004 | error | A story that uses a handle that doesn't exist. |
| CL005 | error | A legacy `.config.json` that fails to render or isn't valid JSON. |
| CL006 | warning | A legacy placeholder (`{ref:}`, `{entry:}`, `{asset:}`) that is no longer resolved. |
| CL007 | warning | A file with both a `component` tag and a legacy config. |
| CL008 | warning | An include whose name is built at runtime, so the check can't follow it. |
| CL009 | warning | An `icon` prop with no icons to pick from (`icons` unset or empty), or whose default isn't one of them. |

Handles count as known when any site has them.
