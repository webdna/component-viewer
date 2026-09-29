# Setup

This guide covers a site that has never had the component library. Upgrading a site that runs
v1? Follow [Upgrading from v1](upgrading-from-v1.md) instead.

## 1. Install

```bash
composer require webdna/component-library:^2.0
php craft plugin/install component-library
```

Install it as a plugin. Don't list it in `config/app.php` as a module: loaded that way, it throws
an error naming the line to remove.

## 2. Config file

Everything is optional. With no file at all, the library looks for components in
`templates/_components` and previews them in a bare page with no styles. To change that, create
`config/component-library.php`:

```php
<?php

return [
    // Folders to look in, in order. A later folder wins when two define the same handle.
    'templateDirectories' => [
        '@templates/_components',
    ],

    // A folder holding one subfolder per site handle, for site versions (below). Optional.
    'sites' => '@templates/_sites',

    // The site template the preview renders inside (section 3). A path, or a map of site
    // handle to path. A site missing from the map gets the plugin's bare layout.
    'layout' => '_components/_preview.twig',

    // Read v1's .config.json files. Leave this off on a new site.
    'legacy' => false,
];
```

| Key | Default | Notes |
|---|---|---|
| `templateDirectories` | `['@templates/_components']` | Aliases or absolute paths. A missing folder is skipped with a log warning. |
| `sites` | none | `<sites>/<current site handle>` is searched **last**, so a file there replaces the shared one on that site only. The folder is never scanned as part of another root. |
| `layout` | the plugin's bare layout | A site template path, or `['siteHandle' => 'path', …]`. |
| `legacy` | `false` | Only for sites upgrading from v1. **A new site leaves it off** and describes its components with the `component` tag alone. With it off, `.config.json` files are never read. |

### Site versions

With `'sites' => '@templates/_sites'`, the file `templates/_sites/uk/ui/button.twig` replaces
`@ui:button` on the site whose handle is `uk`, both on the live site and in the library, which
badges it as a site version. Other sites keep the shared file.

## 3. Preview layout

Previews render on each site's own front end, so they can use the site's real stylesheet. Point
`layout` at a site template that loads your CSS and JS and has two blocks:

```twig
{# templates/_components/_preview.twig #}
<!DOCTYPE html>
<html lang="{{ craft.app.language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {# Load the site's CSS and JS here, e.g. craft.vite.script('src/js/app.js') #}
</head>
<body>
<div class="{% block viewClass %}{% endblock %}">
    {% block component %}{% endblock %}
</div>
</body>
</html>
```

- `component` receives the rendered example.
- `viewClass` receives the component's `viewClass` setting, for padding or a background colour
  around it.

The preview toolbar's *Mode* dropdown shows a component on the site's own background, on
white (`#ffffff`) or on near-black (`#111111`). A component opens on its tag's `background`
([format](format.md)), and a copied address or share link keeps a different choice. On *Site* the
page is exactly your layout. On *Light* or *Dark*, block `component` also holds a link to the
plugin's `preview.css` and a wrapper, `<div class="cl-canvas" data-cl-canvas="light">` (or
`"dark"`), which is `display: contents` so it never changes your layout. That stylesheet sets the
background of `html` and `body` only, with `!important`. Any other element in your layout that
paints its own background still shows, and the component's own colours are untouched. Padding and
centring stay with `viewClass`.

These are v1's block names, so a v1 layout works unchanged. Start the file name with `_` (as
`_preview.twig` above), or keep it in a folder whose name starts with `_`, so Craft doesn't serve it
as a page of its own.

Every preview renders **as a guest**, even for a logged-in user, so a component can't show one
person's account details to another. The preview is a frame on the site's own base URL, so each
site's URL must be reachable from the viewer's browser.

## 4. Permissions

In **Settings → Users → User groups**, under *Component Library*:

| Permission | Grants |
|---|---|
| **Access Component Library** | The library in the control panel. Control-panel access alone does not grant it. |
| **Create and cancel share links** | The *Share links* screen. Nested under the one above. |

Admins hold both.

## 5. Your first component

```bash
php craft component-library/make ui/button
```

This writes `ui/button.twig` and `ui/button.stories.twig` into the first folder in
`templateDirectories`. `--folder` puts them in `ui/button/` instead, and `--root=2` uses the second
folder. Fill them in as the [format guide](format.md) describes. The component shows in the
library on the next page load.

## 6. Caching

The component list is built once per site and kept in Craft's data cache. With `devMode` on, it
rebuilds by itself when a file under a component folder changes. On a production site, deploys
should clear it. Either of these works:

```bash
php craft clear-caches/all
php craft clear-caches/component-library-index
```

It's also under **Utilities → Caches → Component library index**.

## 7. Check in automated builds

```bash
php craft component-library/check
```

It lists every broken component and every include of a handle that doesn't exist, then exits 1 if
there are any. Add `--strict` to fail on warnings too. The [format guide](format.md#the-check)
lists the codes.
