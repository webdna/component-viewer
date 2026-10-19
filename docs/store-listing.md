# Plugin Store listing — draft

**Status:** draft by Claude, 19 Oct 2026. Sam owns the final text, the screenshots and the price.
Nothing here is published until Sam submits it.

**Why the name:** v2 was built as *Component Library*, but the Plugin Store already lists a plugin
with the `component-library` handle, and Craft's update check matches plugins by handle — every
install would have been offered that plugin's updates. It ships as **Component Viewer**
(`webdna/component-viewer`, handle `component-viewer`). A site on an earlier 2.0 beta keeps its
share links and permissions; see `docs/upgrading-from-v1.md`.

## Fields

| Field | Draft |
|---|---|
| Name | Component Viewer |
| Handle | `component-viewer` |
| Package | `webdna/component-viewer` |
| Short description | Browse, try out and share a site's Twig components from the control panel. |
| Categories | Developer Tools · Front end |
| Keywords | components, pattern library, style guide, storybook, twig, design system, preview |
| Edition | One edition, paid: **$99**, renewal **$29**/year |
| Licence | Craft License |
| Requires | Craft CMS 5.0+, PHP 8.2+ |
| Documentation URL | https://github.com/webdna/component-viewer/blob/main/README.md |
| Changelog URL | https://raw.githubusercontent.com/webdna/component-viewer/main/CHANGELOG.md |
| Repository | https://github.com/webdna/component-viewer |
| Icon | `src/icon.svg` (CP section icon: `src/icon-mask.svg`) |

## Long description

Component Viewer lets your team browse, try out and share a site's Twig components — without
building a separate pattern library or running a second toolchain.

### Browse and try out every component

In the control panel, browse every component the site has, by category or by search. Try each
one with its settings and its named examples, previewed in the site's own styling on a desktop,
tablet or phone.

### Share it with a client

A share link lets a client review the same library with no account. Each link has a label and
an expiry, and can be revoked at any moment.

### Components describe themselves

A `{% component %}` block at the top of a component's own file declares its name, status and
props, and renders nothing on the live site. Named examples live in a `.stories.twig` file
beside it. Include a component as `{% include '@ui:button' %}`, or by its path as before.

### The live site can't tell it's there

Apart from being faster: the component list is built once and cached, and every existing way
of including a component keeps working. Multi-site projects get each site's own version of a
component.

### For developers

- `component-viewer/make ui/button` scaffolds a component and its stories file.
- `component-viewer/check --strict` lists broken components and exits non-zero, for CI.
- Access is a separate permission, off by default; control-panel access alone isn't enough.

## Screenshots

From `_store-screenshots/shots/out/component-viewer/`:

1. `01-props.png` — a component's Props tab: what it accepts, documented from its own file.
2. `02-settings.png` — a component tried out live from its Settings tab, in the site's styling.
3. `03-examples-phone.png` — a component's examples, previewed at phone width.
4. `04-share-links.png` — share links with their labels and expiries.
5. `05-client-view.png` — what a client sees through a share link.

## Before submitting

- [ ] Price confirmed: $99, renewal $29
- [ ] Copy reviewed — the table and long description above
- [ ] Screenshots checked against the captions above
- [ ] Packagist: register `webdna/component-viewer`, and mark `webdna/component-library`
      abandoned in its favour
- [ ] GitHub repo name, Packagist and the Plugin Store plugin connected
- [ ] `2.0.0` tagged on `main` with a matching `CHANGELOG.md` heading
