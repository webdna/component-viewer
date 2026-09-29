# Component Library

A Craft CMS 5 plugin for browsing, trying out and sharing a site's Twig components.

- **In the control panel**, the team browses every component a site has, by category or by search,
  and tries each one out with its settings and examples, previewed in each site's own styling on
  a desktop, tablet or phone.
- **Through a share link**, a client reviews the same library with no account. A link has a label
  and an expiry, and it can be cancelled at any moment.
- **Each component describes itself** with a `{% component %}` block at the top of its own file,
  which renders nothing on the live site. Named examples live in a `.stories.twig` file beside it.
- **The live site can't tell it's there**, apart from being faster: the component list is built
  once and cached, and every existing way of including a component keeps working.

Requires Craft `^5.0` and PHP `^8.2`.

## Install

```bash
composer require webdna/component-library:^2.0
php craft plugin/install component-library
```

Then grant **Access Component Library** to the user groups that should see it. Control-panel
access alone isn't enough. [Setup](docs/setup.md) covers the config file, the preview layout and
the permissions.

## A component

```twig
{# templates/_components/ui/button.twig #}
{% component {
    name: 'Button',
    status: 'ready',
    props: {
        label: 'Save',
        style: { type: 'select', default: 'primary', options: ['primary', 'secondary'] },
        disabled: false,
    },
} %}
<button class="btn btn--{{ style ?? 'primary' }}"{{ (disabled ?? false) ? ' disabled' }}>
    {{- label ?? 'Save' -}}
</button>
```

Include it as `{% include '@ui:button' with { label: 'Send' } %}`, or by its path as before.

## Commands

```bash
php craft component-library/make ui/button           # a new component and its stories file
php craft component-library/check [--strict]         # lists broken components, exits 1 if any
```

## Documentation

| Guide | For |
|---|---|
| [Setup](docs/setup.md) | Installing on a new site: config file, preview layout, permissions |
| [Component format](docs/format.md) | The `component` tag, props, stories, handles, site versions, the check |
| [Share links](docs/share-links.md) | Creating, sending and cancelling links for clients |
| [Upgrading from v1](docs/upgrading-from-v1.md) | Moving a site off the v1 module |
| [Resolver](docs/resolver.md) | Finding a site's version of any file from PHP |

[Changelog](CHANGELOG.md)
