# Upgrading from v1

v1 was a module called **Component Library**, package `webdna/component-library`. It is now the
**Component Viewer** plugin, package `webdna/component-viewer`, handle `component-viewer`. The
Plugin Store already has a plugin with the `component-library` handle, so the name had to change.

**Turn legacy reading on first.** v1 components are described by `.config.json` files, and the
viewer reads them only when the config file says so:

```php
// config/component-viewer.php (renamed from config/component-library.php)
return [
    // …your existing v1 settings, unchanged…
    'legacy' => true,
];
```

Without that line, the viewer ignores every `.config.json`, so the library lists only components
that have a `component` tag. That's none, on a site that hasn't converted any. The live site renders
the same either way, but the library would be empty.

With it on, and the steps below done, no template edit is needed. Every page renders as it did on
v1, and every existing include (`@handle`, file paths, site versions) resolves as before.

## Steps

1. **Swap the package.** In `composer.json`, replace `"webdna/component-library": "^1.0@beta"` with
   `"webdna/component-viewer": "^2.0"`, then run `composer update webdna/component-library
   webdna/component-viewer`. The viewer needs Craft 5 and PHP 8.2.

2. **Remove the module from `config/app.php`.** v1 was a module, the viewer is a plugin. Delete the
   `use webdna\componentlibrary\ComponentLibrary;` line, `'component-library' =>
   ComponentLibrary::class` from `modules`, and `'component-library'` from `bootstrap`. If one is
   left in, every request stops with an error naming the lines to remove.

3. **Install the plugin.**

   ```bash
   php craft plugin/install component-viewer
   ```

   This adds the plugin to project config, so commit the `config/project/` change. Other
   environments then install it with `project-config/apply` on deploy, as usual.

4. **Rename `config/component-library.php` to `config/component-viewer.php`** and add
   `'legacy' => true`, as above. Leave the other keys as they are. `templateDirectories`, `sites` and
   `layout` mean what they did, and v1's absolute paths and leading slashes still work. Until it is
   renamed, the old file is still read and a deprecation warning asks for the rename.

5. **Grant the permission.** In v1, everyone with control-panel access could see the library. Now
   only groups with **Access Component Viewer** can (admins always can). Decide which groups keep it.
   Customer-service or editor groups usually shouldn't. Grant **Create and revoke share links** to
   whoever sends the library to clients.

6. **Check.** Open *Component Viewer* in the control panel. Every v1 component is listed, each
   config's `variants` appear as examples, and a site version carries a badge naming its site
(*UK version*, say) when that site is picked. Then run:

   ```bash
   php craft component-viewer/check
   ```

   It reports configs that fail to render (CL005), and every `{ref:}`, `{entry:}` or `{asset:}`
   placeholder, which the viewer no longer resolves (CL006, below).

Your preview layout needs no change: the viewer fills the same `component` and `viewClass` blocks.

## From an earlier 2.0 beta

Betas up to 2.0.0-beta.2 were still called Component Library, handle `component-library`. Craft sees
the renamed plugin as a new one, so:

1. Swap the package as in step 1 above, and rename the config file as in step 4.
2. Run `php craft plugin/install component-viewer`. The install takes over the old plugin's share
   table, so links already sent keep working, including at their old `component-library/share/`
   address, and the two permissions, so groups and users keep what they were granted.
3. Remove the old plugin's entry: `php craft plugin/uninstall component-library --force`. Its table
   is already the viewer's, so nothing is lost.
4. Commit the `config/project/` change.

## What's gone

| v1 | Now |
|---|---|
| The front-end pages at `/component-library` and `/component-library/render`, and the `get-component-info` action | Removed. The library is in the control panel, and clients use share links. |
| `COMPONENT_LIBRARY_VIEW_KEY` and the `?key=` address | Removed. Make a [share link](share-links.md) instead. Delete the variable from `.env`. |
| The `navigation` config key | Ignored. The library groups components by their handle, minus its last part (`@form:fields:text` is under *form:fields*). |
| Formatters | Removed. No site used them. |
| `{ref:…}`, `{entry:…}`, `{asset:…}` placeholders in a config's `context` | No longer resolved: the value shows as written, and the check lists each one (CL006). Put a literal value in its place. `{include:@handle}` still works. |
| Every query parameter overriding a component's context | Only declared settings can be changed, and only as plain text. |
| `readme.md` rendered as Twig | Shown as Markdown, unrendered. |
| Variant handles (`@ui:button--primary`) | Not listed. None of our sites used them. |

The live site gets faster: v1 rescanned every component folder and rendered every config on each
`@handle` include. The viewer builds the list once and caches it.

## Converting a component

Conversion is optional, and one component at a time. Add a `component` tag to the `.twig` file,
move the variants into a `.stories.twig` file, then delete the `.config.json` and `readme.md`.
The [format guide](format.md) describes both files.

| v1 config | Now |
|---|---|
| `name`, `handle`, `status`, `viewClass` | The same keys in the tag. |
| `variables` | `props`. `"string"` stays `string`, `"textarea"` becomes `text`, `"checkbox"` or `"lightswitch"` becomes `bool`, `{ "type": "select", "options": … }` stays a select. |
| `context` | Each prop's default, and the *Default* story's values. |
| `variants` | One `{% story 'Name' with { … } %}` per variant, with only the values that differ. |
| `readme.md` | `notes`, as a string. |

The tag renders nothing, so a converted component renders exactly what it did before. Keep the
template's own `?? default` fallbacks, because the tag's defaults are for the library only. While
a file has both a tag and a config, the tag wins and the check warns (CL007).

A v1 config is Twig, so it may compute values. The tag can't: it takes only literal values. Write
the computed value out, or move the logic into the template.

**Once every component is converted**, remove `'legacy' => true`. The library then stops reading
configs altogether.
