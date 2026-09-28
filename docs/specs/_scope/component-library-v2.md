# Component Library v2 — scope note

**Status:** for sign-off · **Date:** 28 September 2026 · **Full spec:** not yet written

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
- **A viewer in the control panel.** It has a component tree, search, settings controls, the
  rendered output, the source and notes. Every view can be bookmarked or linked. It's usable by
  keyboard and on small screens.
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
- **Up and running in LLL.** Installed, previewing with LLL's styling, with a first set of LLL
  components converted as the working example (see open questions).
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
- **Craft 4 support.** Every site that would use v2 is already on Craft 5.

## What we need from you

- **Which LLL components form the first converted set.** *Blocking the price.* My proposal is a
  button, a text field, a dialog and an empty state. Between them they cover a plain component, a
  form field, a component with inner areas, and a message state.
- **Who designs the viewer.** *Blocking the price.* See the open questions.
- **The approver for this note.** *Not blocking.* I've assumed Sam.
- **The wording shown to people opening a share link**, and on an expired or cancelled link.
  *Not blocking.* A developer can draft it for review.

## Open questions affecting price

1. **How the viewer looks.** It could follow the control panel's own look, which is quickest and
   consistent with the rest of Craft. Or it could have its own design, which needs a designer and
   takes longer to build.
2. **The size of the first LLL set.** Four components prove the approach. Converting more is
   straightforward, but it's project time spent on LLL, not on v2.
