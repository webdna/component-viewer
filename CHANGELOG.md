# Changelog

## 2.0.0 - Unreleased

A rewrite as a Craft 5 plugin. See [Upgrading from v1](docs/upgrading-from-v1.md).

### Added
- A control-panel library: a full-screen workspace with a searchable tree, a preview on each site's
  own styling, desktop, tablet and phone devices with rotate, and a drawer of settings, examples,
  source and notes. Every view has its own address.
- A preview *Background* switch (the site's own, light or dark), and a `background` key in the
  `component` tag for the one a component opens on.
- Share links: expiring, cancellable addresses that open the library with no account.
- The `{% component %}` tag, which describes a component in its own file and renders nothing.
- Stories in `<name>.stories.twig`, which can embed components and fill their blocks.
- `component-library/check [--strict]`, for automated builds.
- `component-library/make <category>/<name> [--root=<n>] [--folder]`.
- The resolver, a stable API for finding a site's version of any file from PHP.
- Permissions: *Access Component Library* and *Create and cancel share links*.
- A *Component library index* option under Clear Caches.

### Changed
- Installed as a plugin, not listed as a module in `config/app.php`, which now throws an error
  naming the line to remove.
- Requires Craft 5 and PHP 8.2.
- The component list is built once per site and cached, instead of on every `@handle` include.
- v1's `.config.json` files are read only with `'legacy' => true` in `config/component-library.php`.
- Previews render as a guest, in a frame on the chosen site's own base URL.
- Values typed into the library reach a component as escaped text, and only for declared settings.
- A legacy `readme.md` is shown as Markdown and no longer rendered as Twig.

### Removed
- The front-end `component-library` and `component-library/render` pages and the
  `get-component-info` action.
- The `COMPONENT_LIBRARY_VIEW_KEY` view key. Share links replace it.
- The `navigation` config key and Formatters.
- `{ref:}`, `{entry:}` and `{asset:}` placeholders in legacy configs. The check reports each one.
- Craft 3 and 4 support.

### Security
- The four issues from the September 2026 review cannot recur: Twig injection from typed values,
  element disclosure through placeholders, path traversal through `?site=`, and a view key that
  let anyone in when unset.
