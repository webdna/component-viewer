# Component Library v2 — scope note

**Status:** for sign-off · **Approver:** Sam · **Date:** 28 September 2026 · **Full spec:** not yet written

## What it is

A rebuilt component library for our Craft sites. The team browses every component in the control
panel, sees it rendered with each site's real styling, and changes its settings to see every state.
Clients and designers can open the whole library from a link we send them. The link expires, we
can cancel it at any time, and they don't need an account. Each component describes its own
settings and examples in its own file. That removes the separate settings file, which currently
has to be kept in step by hand. The live site uses components exactly as it does today, but faster,
and without the security holes found in the September review.

## Who it is for

- **webdna developers:** they build, document and test components, and they install v2 on sites.
- **Designers and project managers:** they review components and their states in the control panel.
- **Clients:** they review the library through a share link, without a control-panel account.

## In scope

- **Rebuilt as a proper Craft plugin, for Craft 5 only.** Access comes from a permission, not a
  shared key.
- **A viewer in the control panel, built in the control panel's own look.** It has a component
  tree, search, settings controls, the rendered output, the source and notes. Every view can be
  bookmarked or linked. It's usable by keyboard and on small screens. Share-link viewers see the
  same design outside the panel.
- **Previews on each site's own address, with its own styling**, including the eight mw-core
  storefronts.
- **Share links that open the whole library.** Each has an expiry and a label, and can be revoked.
  People using a link can change settings but can't browse real site content.
- **A component describes itself.** Its settings, defaults and options are declared at the top of
  its own file.
- **Named examples in a companion file.** They can show a component inside another component, and
  can fill a component's inner areas. That's how LLL builds most of its pages.
- **Components are found wherever they live:** in their own folder or as single files, and whether
  a template refers to them by short name or by path.
- **Existing sites keep working unchanged.** Current component names, folders, per-site overrides
  and settings files are all still read. Converting to the new format is optional and gradual.
- **Faster pages.** The component list is built once and reused, instead of being rebuilt every
  time a component is used.
- **Developer tools:**
  - a command that creates a new component
  - a check that finds broken components and missing references, suitable for automated checks
  - clear error messages in the viewer instead of broken pages
  - automated tests
- **A documented way for other code to find a site's version of a template.** mw-core's search
  cards can use it when convenient.
- **Up and running in LLL.** Installed and previewing with LLL's styling. Three LLL components
  are converted as the working example: the button, the text field and the dialog. Between them
  they cover a plain component, a form field, and a component with inner areas.
- **Share-link wording drafted by the developer and reviewed by Sam before release.** This covers
  the page a link opens and the pages shown for an expired or cancelled link.
- **Documentation:** setup, the component format, share links and upgrading from v1.

## Out of scope

- **Interim security fixes to the current version.** These are shipped separately, now, because
  live sites are exposed until v2 lands.
- **Moving mw-core and webdna onto v2.** Each site upgrades as its own piece of work once v2 is
  released. v2 is built so that upgrading needs no template changes.
- **Converting LLL's remaining components.** This happens as the project touches them, not as part
  of building v2.
- **The "formatters" feature.** Neither site using the library uses it.
- **Showing mw-core's search-card version next to the site version in the viewer.** It's useful,
  but it's new scope and only one site needs it.
- **Publishing on the Craft Plugin Store.** It stays a private package for our own sites.
- **Automated screenshot comparison or accessibility testing of components.** It's a separate tool
  and could be added later.
- **A bespoke viewer design.** The control panel's own look is quicker to build, and it keeps
  Craft's accessibility work.
- **Craft 4 support.** Every site that would use v2 is already on Craft 5.

## What we need from you

- **Sign-off on this note.** Nothing else is outstanding before the full spec.
- **A review of the share-link wording** before release. *Not blocking the build.*

## Open questions affecting price

None. The viewer's look, the first LLL set, the approver and who writes the wording are all
decided above.
