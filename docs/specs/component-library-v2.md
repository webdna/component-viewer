---
spec: Component Library v2
slug: component-library-v2
status: draft
version: 0.24
date: 2026-09-28
author: Claude (for Sam Birch)
client: webdna (internal)
approver: Sam Birch
profile: _PROFILE.component-library.md
related: [_scope/component-library-v2.md]
---

# Component Library v2

> **Status:** draft · **Version:** 0.19 · **Profile:** `_PROFILE.component-library.md`
> The team browses and tries out every component in the control panel, previewed on each site's
> own styling. Clients review the same library through a link that expires and can be cancelled.

---

## 1. How it works

The team can open **Component Library** in the control panel and browse every component a site
has, by category or by search. Opening one shows it rendered with the chosen site's real styling,
next to its settings, examples, source and notes. Changing a setting, example or site updates the
preview in place. The viewer works like Craft's entry preview: it fills the whole screen, with the
list beside the preview. As in v1, the settings, examples, source and notes sit in a row of tabs
along the bottom of the preview, which opens upward. The preview can show the component on a
desktop, tablet or phone, turned either way, always at real size. Every view has its own address, device
included, to bookmark or paste into a ticket. Only people
given the new library permission see it. Control-panel access alone is not enough.

Anyone with the share permission can create a **share link**. It opens the whole library outside
the control panel, in the same design, for someone with no account. A link has a label and an
expiry: two weeks unless changed, three months at most. It can be cancelled at any moment and then
stops working for everyone at once. The address is shown once, at creation, and nothing is emailed.
People on a link can change settings, but whatever they type reaches the component as plain text.
It can never pull real site content into the page.

Each component **describes itself.** A settings block at the top of its own file lists its
settings, defaults and options. Named **examples** live in a companion file beside it. They can
place one component inside another or fill a component's inner areas, which is how LLL builds most
of its pages. The settings block changes nothing on the live site, because only the library reads
it. So converting a component cannot alter a page. Components on the old separate settings file
keep working on a site that turns on legacy reading when it upgrades. Conversion is optional, one
component at a time. A new site leaves legacy reading off and uses the settings block alone.

The idea it rests on: **the live site must not be able to tell v2 from v1, except that it's
faster.** Every existing way of referring to a component keeps working: short names, file paths,
and the per-site versions mw-core's eight storefronts rely on. The component list is built once
and reused. The four holes from the September review are designed out rather than patched:
typed values running as code, content disclosure, a site switch reaching the file system, and a
key that let anyone in when it was unset.

**Rejected alternatives.** A bespoke viewer design (the control panel's look is quicker and keeps
Craft's accessibility work). Keeping the viewer inside the control panel's usual page, or opening
the full-screen view only on demand (Sam chose the preview's full screen every time, for the room).
Reusing Craft's own preview code (it's tied to the entry editor and to scripts the share page must
not load). Applying the settings block's defaults on the live site (that makes
converting a behaviour change). Keeping the view key (it was the fail-open hole). Emailing links
(unasked for, and a mail path to build).

**Vocabulary**

| Term | Means |
|---|---|
| Component | One reusable piece of a page (a button, a card), kept in its own file. |
| Handle | A component's short name, like *ui:button*, which templates use to include it. |
| Setting | One input a component accepts (label, style, size), with a default and sometimes a fixed list of options. |
| Example | A named, saved combination of settings, and possibly surrounding markup. Called a *story* from §4 on. |
| Site version | A per-site copy of a component that replaces the shared one on that site only. |
| Share link | An expiring, cancellable address that opens the whole library without an account. |
| Device | The size the preview pretends to be: *Desktop* (the whole preview area), *Tablet* or *Phone*, each upright or turned. |

---

## 2. Scope

**In scope.** Everything in the agreed scope note, as described in §1 and §3:
- The plugin and its two permissions.
- The control-panel viewer, share-link management and the share viewer.
- Previews on each site's own styling.
- The settings block, the examples file, and full reading of the old settings files.
- The component list built once.
- The create command, the library check, readable errors and automated tests.
- A documented site-version lookup.
- LLL with its three converted components.
- Documentation.

**Out of scope**

| Not doing | Why |
|---|---|
| Interim fixes to v1 | Done and shipped separately, because live sites were exposed. |
| Upgrading mw-core and webdna to v2 | Each site's own piece of work after release. v2 is proved against them here, not deployed. |
| Converting LLL's other 100 components | Done as the project touches them. |
| "Formatters" | Neither site using the library uses them. |
| Old placeholders that pull real content in by id | Unused by both sites, and they were the disclosure hole. |
| The shared view key | It was the fail-open hole. Share links replace it. |
| Any email or notification | The creator sends the link. Nothing asked for more. |
| mw-core's search-card version beside the site version | New scope, one site only. |
| Craft Plugin Store listing | It stays a private package. |
| Screenshot or automated accessibility testing of components | A separate tool. |
| Craft 4 | Every site that would use v2 is on Craft 5. |
| Free widths, zoom control, a size readout, several devices side by side | Craft's preview offers none of them, and three devices plus turning covered the need. |

**Later.** mw-core's search cards adopting the site-version lookup. Screenshot comparison of
examples across sites.

---

## 3. User journeys

**Who this is for**

| Group | What they get |
|---|---|
| webdna developers | Build, document and test components. Install v2. Run the check in automated builds. |
| Designers and project managers | Review components and their states in the control panel, if granted. |
| Clients | Review the library through a share link, with no account. |
| Control-panel editors on client sites (e.g. mw-core customer service) | **Affected:** they lose the library, which v1 showed them by accident, unless it's granted. |
| Site administrators | **Affected:** they choose which groups get the two permissions when a site upgrades. |

**1. Try out a component**
   1. In the control panel, open *Component Library* and find the component in the tree or by search.
   2. See the preview. Open the tab row below it for the settings, examples, source and notes.
   3. Change a setting or pick an example. The preview updates without leaving the page.
   4. Pick another site. The preview reloads in that site's styling. A site's own version is shown and marked.
   5. Pick *Tablet* or *Phone*, and turn it. The preview takes that shape at real size, scrolling
      if it's bigger than the space. Hide the list, close the tabs or drag them taller. All of it is
      remembered next time.
   6. Copy the address. Anyone with the permission who opens it sees the same view, on the same device.

**2. Share the library**
   1. Open *Share links* and choose *New share link*. Enter a label. The expiry is pre-filled two
      weeks out and can be moved up to three months.
   2. Create it. The full address is shown once, with a copy button and a note that it won't be
      shown again.
   3. The list shows label, creator, expiry, status and last use. *Cancel link* asks for confirmation,
      then stops it working straight away, including for anyone who has it open.

**3. Review through a share link**
   1. Open the link. The library appears in the control panel's design, headed with the link's label
      and expiry. There's no login.
   2. Browse, search, change settings, switch examples and sites, as in journey 1.
   3. An expired or cancelled link shows a plain page that suggests asking the sender for a new one.

**4. Add or convert a component**
   1. Run the create command with a name. It writes the component, with an empty settings block,
      and an examples file.
   2. Fill them in. The component appears in the library on the next page load.
   3. Run the library check before committing. It lists anything broken and fails the build if so.

**5. Upgrade a site from v1.** Swap the old module line for a plugin install, add `'legacy' => true`
to `config/component-library.php`, and grant the view permission to the groups that should keep the
library. Pages look exactly as before. Once every component is converted, the line can go.

**First run: the empty state.**
- No components yet: the library says so, names the folders it searched, and shows the create
  command and a link to the format guide.
- A component with no examples shows one *Default* example built from its defaults.
- A component with no settings says "This component has no settings."
- No share links: "No share links yet", with the *New share link* button.
- A share link on an empty library: "Nothing to show yet."

---

## 4. Data model

**`componentlibrary_shares`**, the only new table.

| Field | Type | Notes |
|---|---|---|
| `id`, `uid`, `dateCreated`, `dateUpdated` | Craft standard | |
| `label` | varchar(100), not null | Shown on the list and in the share viewer header |
| `tokenHash` | char(64), unique | SHA-256 hex of the token. The token itself is never stored. |
| `expiresAt` | datetime, not null | End of the chosen day, UTC |
| `revokedAt` | datetime, null | Set on cancel, never cleared |
| `createdById` | int, FK `users.id`, on delete cascade | |
| `lastUsedAt` | datetime, null | Updated at most once a minute per link |

**Why this shape.** Storing only a hash means a database leak doesn't leak working links. It's
also why an address can be shown only once. Status (active, expired, cancelled) is **derived** from
`expiresAt` and `revokedAt`, so it can't drift from the clock. **The component index is not in the
database.** It's derived from the template files and held in Craft's data cache per site (BR-14),
so deleting the cache loses nothing. A **story** (the build name for an example) isn't stored
either. It's read from `<name>.stories.twig` or a legacy config's `variants`, or synthesised as
*Default*.

**On deletion.** Deleting a user deletes their links, so the links stop working. Garbage collection
removes rows 30 days after expiry or cancellation. Uninstalling drops the table.

**States** (derived). *Active* → *Expired* when `expiresAt` passes, or → *Cancelled* when
`revokedAt` is set. Both refuse from the next request, previews included (BR-21, BR-27).

---

## 5. Rules

**Access**

| # | Rule |
|---|---|
| BR-1 | The view permission is Craft's own section permission `accessPlugin-component-library` (the plugin has a CP section). The plugin registers `manageComponentLibraryShares` nested under it. Admins hold both implicitly. `accessCp` alone grants **neither**. *Why Craft's and not our own:* Craft refuses any CP request whose first segment is a plugin handle without `accessPlugin-<handle>` (`web/Application.php`), before a controller runs, so a separate view permission could never be enough on its own. |
| BR-2 | Every CP viewer route requires `accessPlugin-component-library`. Share-management routes and actions also require `manageComponentLibraryShares`. Both are enforced in the controller, not the template. The CP nav item shows only with `accessPlugin-component-library`. |
| BR-3 | Create and cancel are POST-only and CSRF-validated. No controller disables CSRF. The only anonymous actions are the render and share-viewer actions in §6. |
| BR-4 | Loaded as a module (listed in `config/app.php`) instead of installed as a plugin, the class throws `InvalidConfigException` naming the line to remove. It never half-works. |

**Component format**

| # | Rule |
|---|---|
| BR-5 | A component declares itself with `{% component { … } %}` anywhere in its file. Keys: `name`, `handle`, `status` (`prototype`\|`wip`\|`ready`\|`deprecated`), `notes` (Markdown), `viewClass`, `background` (`site`\|`light`\|`dark`, default `site`, BR-41), `props`. Each prop is a shorthand default (`label: 'Save'`, type inferred) or `{type, default, options, required, description}`. Types: `string`, `text`, `bool`, `number`, `select` (needs `options`), `json`. |
| BR-6 | The tag's argument must be a hash of literals, recursively (strings, numbers, booleans, null, arrays, hashes). A variable, filter, function call or concatenation is a Twig **syntax error at compile time**, naming the file and line. The library reads the tag by parsing, never by rendering. |
| BR-7 | The tag compiles to nothing. A converted component renders byte-identical output to the same file without the tag. Defaults feed the viewer only. |
| BR-8 | Stories live in `<name>.stories.twig` beside the component. `{% story 'Name' with { … } %}{% endstory %}` renders the component with those props. A body, if given, renders instead: any Twig, including `include`/`embed` of any component with block overrides. The current props are available to the body as `props`. Names are unique per file, and `with` follows BR-6. |
| BR-9 | Rendering one story executes only that story's body. A `.stories.twig` file is never indexed as a component and is rendered only by the preview. |
| BR-10 | No stories file means one story, *Default*, built from the prop defaults. A legacy config's `variants` become stories named by each variant's `name`, with its `context` merged over the base `context`. |

**Discovery, index and resolution**

| # | Rule |
|---|---|
| BR-11 | Roots are `templateDirectories` from `config/component-library.php` (default `['@templates/_components']`), in order. If `sites` is set, `<sites>/<currentSiteHandle>` is appended **last**. The site handle is always Craft's current site. A missing root is skipped with a log warning, and a missing site folder with an info line. The `sites` folder is never scanned as part of another root. |
| BR-12 | A component is either (a) a `.twig` file containing a `component` tag, pre-filtered by the regex `\{%-?\s*component\b` and then parsed, or (b) a legacy `<name>.config.json` with a sibling `<name>.twig`, **only when the config file sets `legacy` to `true` (default `false`)**. If both describe one file, the tag wins and the check warns. With `legacy` off, configs are never read or rendered, so a converted file is its tag alone. The setting is part of the index cache key. |
| BR-13 | Handle: the tag's `handle`, else the legacy `handle`, else derived from the path relative to its root. Segments are joined with `:` and the extension dropped. A stem equal to its parent folder collapses (`ui/button.twig` → `@ui:button`, `components/button/button.twig` → `@components:button`). Across roots the **later root wins** per handle, which is how site versions work. Within a root, a duplicate keeps the first by sorted path, and the check reports it. |
| BR-14 | The index (handle → file, metadata, props, stories) is built at most once per site per cache lifetime. It sits in Craft's data cache, keyed by site handle and plugin schema version. It's cleared by a *Component library index* Clear Caches option and by `clear-caches/all`. With `devMode` on, it rebuilds when any file under a root is newer than the build (at most one stat walk per request). No include, in any mode, walks a directory. |
| BR-15 | With `legacy` on (BR-12), legacy `.config.json` files are **Twig templates** (`{{ raw({…}\|json_encode) }}`). They're rendered with `renderTemplate()` in site template mode, with an empty context, only during an index build, then JSON-decoded. A failure indexes the component with an error flag and doesn't throw. Keys read: `handle`, `name`, `status`, `context`, `variants`, `viewClass`, and `variables` (mapped to prop types: `"string"` → `string`, `{type:'select', options}` → `select`, and so on). A sibling `readme.md` becomes `notes`, as Markdown, unrendered. A config under the site templates folder renders by its name there. A root outside that folder becomes the templates path for the render, because Craft refuses other names. |
| BR-16 | In legacy defaults, `{include:@handle}` is honoured by rendering that handle with `renderTemplate()` at preview time (webdna uses it twice). `{ref:}`, `{entry:}` and `{asset:}` are **not** resolved. The value stays literal, and the check reports each one. |
| BR-17 | Names the plugin owns match `^@[A-Za-z0-9_-]+(:[A-Za-z0-9_-]+)+$`, and a variant's last segment may contain `--`. Every other name goes untouched to Craft's loader: Twig namespaces (`@ns/path`), plain paths, anything with `/`. An unknown owned handle raises Twig's standard missing-template error, so `ignore missing` works. This applies to `include`, `embed`, `extends`, `source()` and `include()`, in site and CP template modes. |
| BR-18 | Path includes of component files (LLL's `'_components/ui/button.twig'`) resolve through Craft as today. The index maps each file back to its handle, so the viewer lists it. |
| BR-19 | `resolver->resolve(string $path, ?string $siteHandle = null): ?string` returns the absolute path from the highest-precedence root (BR-11 order) containing the relative `$path`, or `null`. A path containing `..` or starting with `/` returns `null`. It's a documented, stable public API. |

**Preview rendering**

| # | Rule |
|---|---|
| BR-20 | The preview is an iframe on the target site's base URL, carrying a Craft token (`tokens->createToken`) routed to the render action. Token params hold only the scope, `user:<id>` or `share:<id>`. The lifetime is 1 hour or the share's remaining life, whichever is shorter, with no usage limit. The component, story and props travel as the query params `component`, `story`, `props`, plus `bg` when the viewer's background isn't the component's own (BR-41, BR-42). |
| BR-21 | Every render request rechecks the scope: the user still exists, is active (not suspended) and holds `accessPlugin-component-library`, or the share is active. On failure it returns 403, "Preview expired, reload the page". |
| BR-22 | The site rendered, and the index used, is the site the request was served on. No request value selects a site for rendering, indexing or a path. The viewer's `site` address parameter only chooses the iframe's base URL, and it's accepted only once `getSiteByHandle()` returns a site (otherwise the primary site). |
| BR-23 | Previews render as a guest. The identity is cleared in memory for the request only, with no session write, so `currentUser` is null whoever's browser it is. |
| BR-24 | `props` is a JSON object of at most 8 KB. Each key is coerced to its declared type: strings ≤ 2,000 chars, text ≤ 10,000, `select` must be one of `options` (else the default), `json` depth ≤ 5. Undeclared keys are dropped. **Every request-supplied string reaches the component as inert, pre-escaped text** (`Twig\Markup` of the HTML-escaped value), so `\|raw` cannot inject markup. It's never passed to `renderString`, placeholder-parsed or used in a path. HTML in props comes only from files. Escaping protects HTML only: a prop the component uses as script (an Alpine expression, a raw attribute string), a URL, a file path or a template name is declared as a `select` or left to stories' `with`, never as a free `string` (found in the LLL pilot, task 6.1). |
| BR-25 | The render extends the configured `layout`: a path, or a map of site handle → path, defaulting to the plugin's bare layout. It fills block `component`, and block `viewClass` when set. These are v1's block names, so mw-core's and webdna's layouts work unchanged. |
| BR-26 | Render responses send `Cache-Control: no-store`, `X-Robots-Tag: noindex`, `Referrer-Policy: no-referrer` and `Content-Security-Policy: frame-ancestors` (CP origin and primary site origin only). A render exception returns 500 with an error panel. In `user:` scope the panel shows the message, template name (relative to templates) and line. In `share:` scope it shows only "This component couldn't be shown." No absolute server path appears in any scope. |

**Share links**

| # | Rule |
|---|---|
| BR-27 | A token is 32 random bytes, base64url (43 chars), looked up by SHA-256 hash and shown once in the create response. The URL is `<primary site>/component-library/share/<token>`. Unknown: 404. Cancelled: 410, cancelled page. Expired: 410, expired page. Active means `revokedAt` is null and `expiresAt` is in the future. |
| BR-28 | The label is required, 1–100 chars, trimmed. The expiry is a date, counted in UTC days: default today + 14 days, minimum tomorrow, maximum today + 90 days, validated server-side. There's no limit on the number of links. |
| BR-29 | The share viewer offers everything the CP viewer does except share management. It shows source and notes, never file paths or roots, and sends `Referrer-Policy: no-referrer`. |
| BR-30 | Nothing is sent: no email, notification or webhook, for any event in this spec. |

**Tooling**

| # | Rule |
|---|---|
| BR-31 | `craft component-library/check [--strict]` prints `<code> <path relative to templates>:<line> <message>` per problem, then `<n> problems`. Errors: CL001 tag unparseable or not literal, CL002 duplicate handle in a root, CL003 unknown handle in a literal `include`/`embed`/`extends` anywhere under `@templates`, CL004 unknown handle in a story, CL005 legacy config failed to render or decode. Warnings: CL006 unresolved legacy placeholder, CL007 tag and config on one file, CL008 dynamic include name skipped. It exits 1 on any error (or any warning with `--strict`), else 0. |
| BR-32 | `craft component-library/make <category>/<name> [--root=<n>] [--folder]` writes `<name>.twig` (a tag with `name` and `status: 'wip'`) and `<name>.stories.twig` (one *Default* story) into the first root, or root *n* (numbered from 1 in `templateDirectories` order; a site folder isn't one). `--folder` nests them in `<name>/`. It refuses to overwrite, writing neither file if either exists, and refuses the other layout of a handle the same root already has (a CL002). It validates segments against `[a-z0-9-]+`, and invalidates the index. |

**Non-functional**

| # | Rule |
|---|---|
| BR-33 | Once the index is warm, a page with 50 `@handle` includes does no directory iteration and no config rendering. A cold build of LLL's 103 files completes within one request. |
| BR-34 | The viewer is built from Craft CP form macros and components. Every control is labelled and keyboard-reachable, with visible focus and a logical order: the tree toggle, the tree, the preview toolbar, the preview, the divider, and the details drawer's tabs, toggle and panel. The device buttons are one group operated by arrow keys, with `aria-pressed`. The drawer's toggle has `aria-expanded`. The divider is a focusable `role="separator"` that the arrow keys move. The iframe `title` names the component, story and device. It's usable at 375 px wide (BR-37). |
| BR-35 | Craft `^5.0`, PHP `^8.2`. Single-site installs show no site switch and otherwise work unchanged. |
| BR-36 | A share row holds a label, the creator id and timestamps, and nothing about the recipient. |

**Viewer workspace** (added in v0.16, modelled on Craft's entry preview)

| # | Rule |
|---|---|
| BR-37 | Both viewers are one full-viewport page, with no CP nav, CP header or page scroll. It has a header, then the tree beside the preview, and below the preview the details drawer, as in v1: a row of tabs (Settings, Examples, Source and Notes) that opens upward. A tab opens the drawer on that tab, the open tab closes it, and a toggle at the row's end does either. The drawer starts closed. The CP header carries the component name, a link back to the control panel and, with `manageComponentLibraryShares`, *Share links*. The share header is unchanged (label and expiry). The CP page keeps every gate of BR-1 and BR-2. Below 768 px the tree stacks above the preview, behind its toggle, and an open drawer takes the height its content needs. Nothing scrolls sideways at 375 px. |
| BR-38 | The preview toolbar has the device group (*Desktop*, *Tablet* 768×1024, *Phone* 375×667), *Rotate* (off for Desktop), the site switch (BR-35), *Refresh* and *Open in a new tab*. The site switch changes site as soon as a site is picked. Its button shows only without script. *Desktop* fills the preview area. Tablet and phone always render at their real size, 100%, inside a bezel, and landscape swaps the two sizes. A device bigger than the preview area scrolls there, centred when it fits, and is never scaled. The bezel is plugin-owned CSS drawing Craft's published preview SVGs. It never uses Craft's `lp-*` classes, `Craft.Preview` or any CP script, which the share page doesn't have (§6 *Screens*). *Open in a new tab* opens the current preview URL, whose token lapses as BR-20 says. |
| BR-39 | The address carries `device` (`desktop`\|`tablet`\|`phone`) and `orientation` (`portrait`\|`landscape`) beside `story` and `props`, and a copied address restores them. Any other value, or none, means desktop portrait. Neither value reaches the server's render, a path or a template name. Only the viewer page reads them. |
| BR-40 | The tree's hidden state, whether the drawer is open, and the open drawer's height are remembered per browser under keys prefixed `cl.`, with read failures ignored, and the page works without them. The divider sits on top of an open drawer. The drawer defaults to 40% of the height, at least 120 px, and the preview keeps at least 200 px. Dragging, the arrow keys or a double-click (which resets) change it. Below 768 px neither the tree's state nor the height applies. |

**Preview background** (added in v0.21, Sam's request)

| # | Rule |
|---|---|
| BR-41 | The preview background is `site` (the layout's own, untouched), `light` or `dark`. A component opens on its tag's `background` (BR-5, default `site`). The preview toolbar has a *Mode* dropdown (*Theme* for `site`, *Light*, *Dark*) after the device group, in both viewers, laid out like the site switch, with the value in effect selected. A change reloads the iframe and puts `bg` in the address beside `device` and `orientation` when it isn't the component's own `background` (and takes it out when it is), and a copied address restores it. A story switch keeps `bg`. Picking another component in the tree drops it, so each component opens on its own `background`. The choice is not remembered per browser, because that would hide every component's own setting. |
| BR-42 | The render reads `bg` as exactly `site`, `light` or `dark`. Anything else (an array, markup, another case, any other string) means the component's `background`. `site` is a choice of its own (v0.22), so *Theme* works on a component whose tag says `dark`. When the value in effect is `light` or `dark`, block `component` begins with a `<link rel="stylesheet">` to the plugin's published `preview.css` and wraps the story in `<div class="cl-canvas" data-cl-canvas="light\|dark">`. The class and attribute come from that fixed pair, never from the request. `preview.css` is the plugin's own base stylesheet for the preview. The wrapper is `display: contents`, so it never changes layout, and `html` and `body` of a page holding it get the background with `!important` (light `#ffffff`, dark `#111111`). The component's own colours are untouched. With `site`, the render is byte-identical to one without this feature: no link and no wrapper. A layout element other than `html` or `body` that paints its own background still shows. Container layout (padding, centring) stays with `viewClass`. |

---

## 6. Interfaces

| Method | Path | Purpose | Auth | Returns |
|---|---|---|---|---|
| GET | `admin/component-library` | Viewer: first component or empty state | `accessPlugin-component-library` | CP page |
| GET | `admin/component-library/<handle>?story=&site=&props=&device=&orientation=&bg=` | Viewer on one component (`site` picks the iframe base URL only, per BR-22; `device` and `orientation` per BR-39, and `bg` per BR-41, also on share URLs) | `accessPlugin-component-library` | Full-screen CP page (BR-37) |
| GET | `admin/component-library/shares` | Share list and create form | + `manageComponentLibraryShares` | CP page |
| POST | `actions/component-library/shares/create` | Create a link | + manage, CSRF | Redirect, one-time URL in the flash |
| POST | `actions/component-library/shares/revoke` | Cancel a link | + manage, CSRF | Redirect |
| GET | `<primary site>/component-library/share/<token>[/<handle>]` | Share viewer | Anonymous, active token | Page / 404 / 410 |
| GET | `<site base URL>?token=<craft token>&component=&story=&props=[&bg=site\|light\|dark]` | Preview render (`bg` per BR-42) | Anonymous, valid Craft token, scope rechecked | HTML / 403 / 500 |

v1's front-end `component-library` and `component-library/render` URLs and the
`get-component-info` action are **removed**.

**Screens**

| Surface | New or reuse | Notes |
|---|---|---|
| Viewer (CP) | New, full-screen workspace (BR-37) | Not on the CP layout: a bare CP page with Craft's CP stylesheets. Header, then a collapsible tree and search beside the preview with its toolbar (BR-38), and under the preview the details drawer (tabs Settings, Examples, Source and Notes, driven by the viewer's own script) with a draggable divider on top when open. Site-version and duplicate badges in the header. |
| Share links (CP) | New, CP table and form | One-time URL panel with copy. Cancel with confirmation. |
| Share viewer | New, same templates outside the CP | CP stylesheets only (Craft's reset, CP theme and `cp.css`, published from Craft's own folders), none of the CP's JS: `CpAsset` would write the visitor's email and user id into `window.Craft` on a public page. No CP nav. The same workspace as the CP viewer (BR-37), with the header showing label and expiry. |
| Expired, cancelled and unknown link pages | New | Plain: one sentence and a suggestion |
| Error panel | New | Inside the preview. Detail depends on scope (BR-26). |

**Design source.** No bespoke design. The developer builds in Craft's CP look with its form macros,
and the share viewer reuses the same templates and CP stylesheet. The workspace follows Craft 5's
entry preview screen: its full screen, 44 px pane headers, device buttons, rotate and bezels
(`Craft.Preview`, read from `cp/dist/cp.js.map`, and its `_preview.scss`), but not its
scale-to-fit (Sam: previews always at 100%). The details drawer below the preview follows v1's
bottom panel. Both viewers share one workspace template and one script, so they can't drift apart.

**Copy ownership.** The developer drafts all copy. Sam reviews the share-link wording before
release: the viewer header, the expired, cancelled and unknown pages, and the one-time URL notice.

---

## 7. Test plan

### Client-verified criteria

| # | Given / When / Then | Proved by |
|---|---|---|
| AC-1 | A CP user without the library permission sees no *Component Library* item, and its address is refused. A user with it can browse. | TS-1 |
| AC-2 | Changing a setting or example updates the preview without leaving the page. Reopening the copied address restores it. | TS-2 |
| AC-3 | Switching site shows that site's styling, and the site's own version, marked, where one exists. | TS-3 |
| AC-4 | A new share link defaults to two weeks, can't exceed three months, and its address is shown once. | TS-4 |
| AC-5 | A share link opens the whole library with no login, and settings can be changed there. | TS-5 |
| AC-6 | A cancelled link stops working at once, including an open preview. An expired link shows the expired page. | TS-6 |
| AC-7 | On a share link, typed values show as plain text. They can't pull in site content or add markup. | TS-7 |
| AC-8 | In LLL, the button, text field and dialog appear with LLL's styling and examples, including a dialog with custom inner content. | TS-8 |
| AC-9 | After upgrading, mw-core's and webdna's pages render as before, with no template changes. | TS-9 |
| AC-10 | A broken component shows a readable error in its preview, and every other component still works. | TS-10 |
| AC-11 | The library check lists a broken component and a reference to a missing one, and fails. On a clean library it passes. | TS-11 |
| AC-12 | The create command's component appears in the library on the next page load. | TS-12 |
| AC-13 | A page using many components doesn't rebuild the component list on each use. | TS-13 |
| AC-14 | The whole viewer works by keyboard alone and on a phone-width screen. | TS-14 |
| AC-15 | The viewer fills the screen like Craft's entry preview, with v1's row of tabs below the preview. The preview switches to tablet or phone, turns, and always shows at real size. A copied address reopens on the same device, and a share link behaves the same way. | TS-15 |
| AC-16 | The preview background switches between the site's own, light and dark without changing the component. A component can say which it opens on. A copied address or a share link keeps the choice. | TS-16 |

### Test data and preconditions

| Fixture | Exists? |
|---|---|
| Sandbox `~/projects/craft5`, admin via `users/impersonate admin` | Yes |
| `restricted` user: has `accessCp`, lacks the new permission, so it is the refused user | Yes |
| Group `clViewers` with `accessCp` and `accessPlugin-component-library` only, and user `clviewer` | Yes, `tests/fixtures/setup.sh` |
| Second sandbox site `second`, base URL `$PRIMARY_SITE_URL/second/` | Yes, `tests/fixtures/setup.sh` |
| Sandbox roots at `tests/fixtures/templates` (and `_sites`), no v1 module lines in `config/app.php` | Yes, `tests/fixtures/setup.sh` |
| `tests/fixtures/templates/`: `good` (tag + stories), `nested` (a story embedding another component with a block override), `bad-tag`, `legacy/button` (Twig-wrapped config with `variants`, `variables`, `{include:}` and a readme), `throws`, `raw-prop` (`\|raw` on a string prop), `_sites/second/` overriding `good`, and a page with 50 `@handle` includes. Plus `tests/fixtures/edge/`, a root outside the sandbox config (TN-8's duplicates, and in `edge/legacy/` TN-9's failing config, `{ref:}`/`{entry:}`/`{asset:}` placeholders, and a tag beside a config) | Yes (2.1 to 3.1), with the 50-include page at `pages/fifty.twig`. 3.1 also added `ui/guest` (prints `currentUser`, for TN-16) and `tests/fixtures/layouts/v1.twig`, a layout on v1's block contract outside every root. 5.1 added `edge/pages/{references,extended}.twig` (CL003, CL008 and the references that must pass) and `edge/refs/panel` (CL004 in its stories) |
| Active, expired and cancelled share rows | Created per test |
| mw-core and webdna local DDEV sites on v1 | Yes. Read-only, for TS-9. |

### Scenarios

**TS-1 · Permission gate** · AC-1 · BR-1, BR-2, BR-3 · *Pest + curl*
*Success criterion: each role gets exactly these responses.*
1. As `restricted`, GET `admin/component-library`. Expect 403, and no nav item on the dashboard.
2. As `restricted`, GET `…/shares` and POST `shares/create`. Expect 403, and no row.
3. As `clviewer`, GET `admin/component-library`. Expect 200 with the tree, no *Share links* tab, and `…/shares` returning 403.
4. As admin, GET `…/shares`. Expect 200.

**TS-2 · Try a component** · AC-2 · BR-8, BR-24 · *browser*
*Success criterion: the reopened view matches exactly.*
1. As admin, open `good` and set `label` to "Hello". The preview shows "Hello" within 1 s, and the page doesn't navigate.
2. Pick *Secondary*. The preview and controls switch, and the address carries `story` and `props`.
3. Open the copied address in a new tab. The same story and "Hello" appear.

**TS-3 · Site styling and site version** · AC-3 · BR-11, BR-13, BR-20, BR-22, BR-25 · *browser*
*Success criterion: markup and stylesheet both follow the site.*
1. Open `good` and switch to `second`. The iframe loads from `second`'s base URL and shows the `_sites/second` version with `data-cl-site-version`.
2. Switch back to `default`. The shared version appears, with no badge.
3. mw-core (manual, v2 on a local branch): `@branding:logo` on `adv1`, then `forgestar`, shows two different logos, each in its site's CSS.

**TS-4 · Create a share link** · AC-4 · BR-27, BR-28, BR-30, BR-36 · *Pest + browser*
*Success criterion: default, cap, show-once and no-send all hold.*
1. Open *New share link*. The expiry is pre-filled today + 14.
2. Submit expiry today + 91. Expect a validation error and no row.
3. Submit "Acme" with today + 14. The one-time URL is shown. The row holds a 64-char hash and no token, and there are no recipient columns. No mail is queued.
4. Reload the list. The URL is not shown again, and the status is Active.

**TS-5 · Use a share link** · AC-5 · BR-20, BR-26, BR-29 · *Pest + browser*
*Success criterion: full browsing with no account.*
1. In a cookie-less browser, open the share URL. Expect 200, the tree, and the header "Acme · expires <date>". No file paths appear anywhere on the page.
2. Open `good` and change `label`. The preview updates, and its iframe carries a `share:`-scoped token.
3. Response headers: the share page has `Referrer-Policy: no-referrer`, and the preview adds `no-store` and `noindex`.

**TS-6 · Cancel and expiry** · AC-6 · BR-21, BR-27 · *Pest*
*Success criterion: refusal takes effect at the next request, with no grace period.*
1. With a preview open under a link, cancel that link, then reload the preview. Expect 403, "Preview expired".
2. GET the share URL. Expect 410 and `data-cl-share-state="cancelled"`.
3. GET an expired link: 410 `expired`. GET an unknown token: 404.

**TS-7 · Hostile values** · AC-7 · BR-16, BR-22, BR-24 · *Pest*
*Success criterion: no request value changes anything but a declared prop's text.*
1. In share scope, render `raw-prop` with `props={"body":"<script>x</script>"}`. The output contains `&lt;script&gt;` and no `<script>x`.
2. Render `good` with `label` set to `{ref:entry:1:title}`, then to `{{ 7*7 }}`. Both appear literally: no `49`, no entry title.
3. Add `?site=../../etc` and an undeclared prop `foo`. The output is identical to the output without them.
4. Send a 9 KB `props`. Expect 400.

**TS-8 · LLL pilot** · AC-8 · BR-5, BR-7, BR-8, BR-9, BR-18, BR-25 · *manual browser + curl*
*Success criterion: all three appear with styling, controls and working examples, and the site's pages are unchanged.*
1. Before converting, save the HTML of three LLL pages using the pilot components. After converting, `diff` them: nothing but volatile tokens should differ (BR-7).
2. Open `@ui:button`, `@form:text` and `@ui:dialog`. Each is styled by LLL's Tailwind, and the settings match the old "Params:" headers.
3. Open the dialog's *Custom panel* story. It's open and shows the story's own panel, not the confirm preset. Only that story's markup is in the iframe.
4. `component-library/check` in LLL reports `0 problems`.

**TS-9 · Back-compat on consumers** · AC-9 · BR-10, BR-15, BR-17, BR-25 · *manual script*
*Success criterion: no template edit needed, and the diffs are empty once tokens are normalised.*
1. On mw-core with v1, fetch 3 URLs per site (24) and save them.
2. Switch to v2 on a throwaway local branch (the `config/app.php` swap, plugin install and `'legacy' => true` only), fetch again and `diff`. Only CSRF tokens and asset hashes should differ.
3. Repeat on webdna with 10 URLs.
4. Open the CP library on mw-core as admin. All 147 legacy components are listed, variants appear as examples, and site versions are badged.

**TS-10 · Readable errors** · AC-10 · BR-15, BR-26 · *Pest*
*Success criterion: one broken component affects only itself.*
1. Preview `throws` in user scope. Expect 500, with a panel showing the message, relative template name and line, and no absolute path.
2. The same in share scope. Expect 500 with "This component couldn't be shown." and no detail.
3. Preview `good`. Expect 200.

**TS-11 · Library check** · AC-11 · BR-6, BR-31 · *Pest*
*Success criterion: codes, exit statuses and the final line match.*
1. Check over the fixture roots. Expect lines for CL001 (`bad-tag`), CL003 (a missing handle) and CL006 (`{ref:}`), then exit 1.
2. Check over `good` alone. Expect `0 problems`, exit 0.

**TS-12 · Create command** · AC-12 · BR-10, BR-32 · *Pest*
*Success criterion: the new component is visible without any other step.*
1. `make ui/badge` writes two files, and the check reports 0 problems.
2. The viewer lists `@ui:badge` with a *Default* story.
3. `make ui/badge` again refuses and leaves the files unchanged.

**TS-13 · Index built once** · AC-13 · BR-14, BR-33 · *Pest*
*Success criterion: the counts are exact.*
1. Clear caches and render the 50-include page. The build counter is 1.
2. Render again with `devMode` off. The counter is 0, and no directory iterator is constructed.
3. With `devMode` on, touch a component file and render. The counter is 1.

**TS-14 · Keyboard and small screen** · AC-14 · BR-34, BR-35, BR-37 · *manual browser*
*Success criterion: journey 1 is complete by keyboard and at 375 px.*
1. Tab from the top through the tree toggle, search, tree, preview toolbar, preview, divider, and the drawer's tabs, toggle and controls. Every control is reachable with visible focus in a logical order, and Enter or Space operates each. The arrow keys move between devices, between tabs, and move the focused divider.
2. At 375 px, do journey 1. The tree is behind its toggle above the preview, the open drawer sits below it, and there's no horizontal page scroll.

**TS-15 · Preview workspace** · AC-15 · BR-37, BR-38, BR-39, BR-40 · *Pest (step 1, 5) + browser*
*Success criterion: device, size, drawer and address agree, in both viewers.*
1. Load the viewer on `good`, and a share link on `good`. Each page has `data-cl-workspace`, three `data-cl-device` buttons, `data-cl-rotate`, `data-cl-divider`, `data-cl-drawer`, `data-cl-drawer-toggle`, `data-cl-tree-toggle`, `data-cl-refresh` and `data-cl-open`, the preview before the tabs, and no CP nav (`#global-sidebar`). The CP page links to *Share links* for admin and not for `clviewer`.
2. Pick *Phone*. The preview is 375×667 inside a bezel at 100%, and *Phone* has `aria-pressed="true"`. *Rotate* makes it 667×375. On *Desktop*, *Rotate* is disabled and the preview fills its area.
3. Open *Settings*, drag the divider up until the tablet no longer fits, and scroll the preview. The tablet stays 768×1024, and its top isn't cut off. The divider stops with 200 px of preview left. Click *Settings* again, and the drawer closes to its row.
4. The address now carries `device=tablet&orientation=landscape`. Open it in a new tab. The same device, orientation, story and props appear.
5. Open `?device=watch&orientation=sideways`. The viewer shows desktop portrait with no error, and neither value appears in the page.
6. Hide the tree, open the drawer and move the divider, then reload. All three are kept. Clear the site data and reload. The defaults return and the page works.
7. *Refresh* reloads the iframe. *Open in a new tab* opens the preview URL on its own.
8. Repeat steps 2, 4 and 7 on the share link.

**TS-16 · Preview background** · AC-16 · BR-5, BR-41, BR-42 · *Pest (steps 1-3) + browser*
*Success criterion: the background changes and nothing else does.*
1. Render `good` with no `bg`, then with `bg=site`. Both are byte-identical to the render before task 3.4, with no `preview.css` link and no `data-cl-canvas`.
2. Render `good` with `bg=light`, then `bg=dark`. Block `component` begins with the `preview.css` link, and the story sits in `data-cl-canvas="light"` (then `"dark"`). The story's own markup is unchanged inside it.
3. Render the edge fixture `on-dark` (its tag sets `background: 'dark'`) with no `bg`. It's on `dark`. With `bg=light` it's on `light`. A tag with `background: 'purple'` is a compile error naming the file and line, and check CL001 (BR-6).
4. In the CP viewer on `good`, pick *Light* from *Mode*. Within 1 s the preview's page background is white, the component looks as it did, *Mode* shows *Light*, and the address carries `bg=light`. Open the copied address in a new tab. It's still light. Pick *Theme*. The layout's own background returns and `bg` leaves the address.
5. With *Dark* picked, switch story. It stays dark. Pick another component in the tree. It opens on its own `background`.
6. Repeat steps 4 and 5 on a share link.

### Negative and edge cases

| # | Condition | Expected behaviour |
|---|---|---|
| TN-1 | Render with no token, a garbage token or an expired Craft token | Craft's 400, or 403. No render. |
| TN-2 | User-scope token whose user lost the permission or was deleted | 403 (BR-21) |
| TN-3 | Share-scope token after its share expired mid-session | 403 (BR-21) |
| TN-4 | A forged `scope` query parameter | Ignored. Scope comes only from the server-side token row (BR-20). |
| TN-5 | `ignore missing` include of an unknown handle | Renders nothing, no error (BR-17) |
| TN-6 | `@ns/file.twig` namespace and a `_components/ui/button.twig` path | Resolved by Craft exactly as without the plugin (BR-17, BR-18) |
| TN-7 | `{% component { name: foo } %}` | Compile error naming the file and line. Check CL001 (BR-6). |
| TN-8 | Two components with one handle in one root | First by path wins, check CL002, "duplicate" badge (BR-13) |
| TN-9 | Legacy config that fails to render | Listed with an error flag, preview panel, site page unaffected (BR-15) |
| TN-10 | Configured root missing on disk | Log warning, other roots indexed (BR-11) |
| TN-11 | Share create without CSRF, or as `clviewer` | 400 / 403, no row (BR-2, BR-3) |
| TN-12 | Label empty or 101 chars, expiry today or in the past | Validation errors (BR-28) |
| TN-13 | Deleting a share's creator | Row deleted, and the URL returns 404 |
| TN-14 | Plugin class still listed as a module | `InvalidConfigException` naming the fix (BR-4) |
| TN-15 | Single-site install | No site switch, previews work (BR-35, the db4ca44 regression) |
| TN-16 | Preview opened by a browser logged in on that front end | `currentUser` null in the render, and still logged in afterwards (BR-23) |
| TN-17 | `resolver->resolve('../config/db.php')` or `'/etc/passwd'` | `null` (BR-19) |
| TN-18 | `device` or `orientation` set to markup, a 5 KB string or an array, on the CP and share viewers | Desktop portrait. The value isn't echoed and doesn't reach the render URL (BR-39) |
| TN-19 | Browser storage blocked, or holding a garbage `cl.` value | Defaults, and no script error (BR-40) |
| TN-20 | `bg` set to markup, `light<script>`, a 5 KB string or an array, on the render and on the CP and share viewers | The component's own `background`. The value isn't echoed, and no class, attribute or path is built from it (BR-41, BR-42) |

### Automated checks

Pest 2 with `markhuot/craft-pest-core` 3 (`tests/`) covers every scenario marked *Pest* and every
TN row, plus unit tests for handle derivation (BR-12, BR-13), story isolation (BR-9) and the legacy
`variables` mapping (BR-15). The suite runs from the sandbox root: craft-pest-core boots Craft
with `CRAFT_BASE_PATH = getcwd()`, and Pest loads `tests/Pest.php` only from `--test-directory`,
so B5 #3 passes both `-c` and `--test-directory`. Each test runs in a rolled-back transaction.
Fixtures that must outlive a test come from `tests/fixtures/setup.sh`, and `tests/HarnessTest.php`
fails if they are missing. TS-3 step 3, TS-8 and TS-9 run against other projects' real pages.
TS-14 needs a human judgement on focus order. TS-2, TS-3 steps 1–2, TS-15 steps 2–4 and 6–8 and TS-16 steps 4–6 stay browser runs until an
e2e runner targets the plugin (the sandbox has Playwright, but nothing points at the plugin yet).

**Test hooks the build must add:**
- `data-cl-component="<handle>"` (tree items), `data-cl-story="<name>"`, `data-cl-prop="<name>"`
- `data-cl-preview` (iframe), `data-cl-error` (error panel), `data-cl-site-version` (badge)
- `data-cl-share-state="active|expired|cancelled"`, `data-cl-share-url` (one-time URL)
- `data-cl-workspace` (the page root), `data-cl-device="desktop|tablet|phone"` (buttons), `data-cl-rotate`,
  `data-cl-divider`, `data-cl-drawer`, `data-cl-drawer-toggle`, `data-cl-tree-toggle`, `data-cl-refresh`,
  `data-cl-open`, `data-cl-site-submit`. These replace 3.2's `data-cl-width`.
- `data-cl-background` (the toolbar's *Mode* select, options `site|light|dark`) and `data-cl-canvas="light|dark"` (the render's wrapper)
- A test-only index build counter, reset per test

### Regression checks

| Journey | Why it is at risk |
|---|---|
| mw-core `@handle` includes on all 8 sites | New loader, and re-implemented site precedence (TS-9) |
| mw-core dynamic `@blocks:#{block.type\|kebab}` includes | Invisible to the check, and must still resolve (BR-17) |
| mw-core `include("_sites/#{currentSite.handle}/head.twig", ignore_missing=true)` | A plain path the loader must pass through untouched |
| webdna's two `{include:@…}` legacy defaults | Placeholder support narrows (BR-16) |
| Ordinary CP pages and other plugins' CP templates | v1 swapped the whole Twig loader. A v2 mistake here breaks the CP. |
| Viewer and share viewer journeys 1 and 3 (TS-2, TS-3, TS-5) after 3.3 | 3.3 moves every viewer control and the tabs into a new layout, and the share page loses its own tab script. |
| LLL's 618 path includes and 61 embeds | The pilot edits three heavily used components (BR-7, TS-8 step 1) |

---

## 8. Build order

| Phase | What it delivers | Depends on |
|---|---|---|
| 1 | Plugin skeleton, permissions, table, test harness and sandbox fixtures | — |
| 2 | Component model: tag, stories, index, legacy adapter, loader, resolver | 1 |
| 3 | Preview render and CP viewer | 2 |
| 4 | Share links and share viewer | 3 |
| 5 | Check and make commands, docs | 2 (beside 3 and 4) |
| 6 | LLL adoption and the consumer back-compat proof | 3, 4, 5 |

The access gate and harness come first, so nothing is ever reachable ungated in the sandbox. Shares
follow the render because they reuse its token scope. Phase 6 is last because it's the only phase
touching other repositories. Task 3.3, the preview workspace, was added after phases 3 to 5 were
built (v0.16). It's built next, before 5.2, because it reshapes both viewers, and 6.1's pilot is
reviewed in it. Task 3.4, the preview background, was added in v0.21 while 6.1 awaited Sam's
review. It's built next, before 6.1 is installed in LLL, so the pilot's tags can use `background`.

### Tasks

- [x] **1.1 Plugin skeleton**: `composer.json` (`craft-plugin`, `^5.0`, `^8.2`, `extra.handle`), `src/ComponentLibrary.php` (Plugin, permissions, CP nav, module-registration guard), `src/migrations/Install.php`, `src/records/ShareRecord.php`. Delete v1's `src/base/`, `src/helpers/`, `src/controllers/ComponentViewerController.php`, and the v1 templates and assets.
      Rules: BR-1, BR-2, BR-4, BR-35 · Verify: B5 #1-3, TN-14
      *As built:* also deleted v1's `src/twig/` (it called the removed `formatters` service) and `src/config.php` (v1 keys). `phpstan.neon` and `ecs.php` landed here so B5 #1-2 could run. B5 #3 waits for 1.2's harness.
- [x] **1.2 Harness and sandbox fixtures**: `tests/Pest.php`, `phpunit.xml`, extend `phpstan.neon` and `ecs.php` to `tests/`, `tests/fixtures/setup.sh` (group `clViewers` + user `clviewer`, site `second`, roots pointed at the fixtures, sandbox `config/app.php` module lines removed). Run `ddev snapshot` first. Settle Appendix A row 3 here.
      Rules: — · Verify: B5 #1-3 green on an empty suite
      *As built:* the suite isn't empty. `tests/HarnessTest.php` proves `tests/Pest.php` was loaded (the plain `pest plugins/component-library/tests` form silently skips it), that the plugin is installed and that the fixtures exist. `setup.sh` does the file edits and runs `tests/fixtures/setup.php` in DDEV for the group, user, site and plugin install through Craft's APIs, ending with `ProjectConfig::flush()`. `clViewers` also needs `accessCp`: Craft drops a nested permission whose parent is missing. `tests/fixtures/templates/` is an empty root, and later tasks add its fixtures. PHPStan ignores `Undefined variable: $this` in `tests/` only (Pest binds closures at runtime).
- [x] **1.3 Permission tests**: `tests/Feature/AccessTest.php`
      Rules: BR-1, BR-2, BR-3 · Verify: TS-1, TN-11
      *As built:* BR-2 and BR-3 live in controllers, so this task added them with their final gates:
      `src/controllers/ViewerController.php` (`index`) and `src/controllers/SharesController.php`
      (`index`, `create`, `revoke`), plus CP URL rules and placeholder templates `viewer/index.twig` and
      `shares/index.twig`. 3.2 and 4.1 fill the action bodies and must keep each `beforeAction()`.
      `create` and `revoke` throw a 500 until 4.1. TS-1 step 3's "with the tree" is asserted in 3.2
      (only 200 and no *Share links* link here). A non-POST to a share action is **405**, not 400
      (Craft 5 `requirePostRequest()`). `tests/Pest.php` adds Yii to PHPUnit's exclude list, because
      Collision counted Yii's `@`-silenced FileCache warnings on every request. A mutation run with
      the manage check removed failed exactly the 4 viewer-vs-manager tests.
- [x] **2.1 `component` tag**: `src/twig/ComponentTokenParser.php`, `src/twig/ComponentNode.php` (compiles to nothing), `src/models/{Component,Prop}.php`, the literal-only validator
      Rules: BR-5, BR-6, BR-7 · Verify: TN-7, Pest `TagTest`
      *As built:* the validator is `src/twig/Literal.php` (`toValue()`, reused by 2.2's `with`), and
      `src/twig/Extension.php` registers the tag through `View::registerTwigExtension()`, so site and
      CP modes both have it. The node holds the `Component` model as an attribute, and
      `ComponentNode::find()` pulls it from a parsed tree: that's how 2.3 reads a file without rendering
      it. Beyond BR-6, a hash that doesn't describe a component is also a compile-time `SyntaxError`
      (unknown key, bad `status` or type, `select` without `options`, a default of the wrong type or
      outside its options, a second tag in one file). A tag `handle` may omit the `@` and is stored
      with it. A prop hash is the full form only when it has `type` and no other keys, otherwise it's a
      `json` shorthand default. Fixtures are `tests/fixtures/templates/ui/{good,bad-tag}.twig`, under
      `ui/` so derived handles have a `:`. A mutation run (node emitting output, validator accepting
      anything) failed 16 of TagTest's 41 tests.
- [x] **2.2 `story` tag and stories file**: `src/twig/StoryTokenParser.php`, `src/twig/StoryNode.php`, `src/models/Story.php`
      Rules: BR-8, BR-9, BR-10 · Verify: Pest `StoryTest` (`nested` fixture)
      *As built:* a story body that isn't blank compiles into a block of its stories file
      (`cl_story_<n>`, on `Story::$block`), and the tag itself compiles to nothing. So
      `Story::render()` runs `renderBlock()` on that one block, and BR-9 holds by construction: no
      other story, and nothing at the file's top level, executes. A blank body (whitespace or
      comments) has no block and renders the component with the props as its context. That's the
      same path as BR-10's `Story::fromDefaults()` and 2.4's legacy variants. `render()` takes the
      final props: merging defaults, the story's `with` and request values, and coercing them, is
      3.1's. `StoryNode::findAll()` reads a parsed file's stories by name, in file order, for 2.3.
      Beyond BR-8, these are `SyntaxError`s too: a story outside a `.stories.twig` file, inside a
      block, macro or another story (embeds included), a missing or non-string name, a `with`
      that isn't a hash, and a missing `endstory`. `with` is optional. The fixtures
      `ui/good.stories.twig` and `ui/nested.{twig,stories.twig}` refer to components by handle
      (`@ui:good`), so they resolve in Craft once 2.5's loader lands. StoryTest renders them
      through an `ArrayLoader` keyed by handle and path. `FIXTURES`, `twigIn()`, `parseTag()` and
      `parseStories()` moved to `tests/Pest.php`, which PHPStan now scans. A mutation run (the whole
      file rendered instead of one block, `with` not literal-checked) failed 13 of StoryTest's 29
      tests.
- [x] **2.3 Index service**: `src/services/Index.php` (roots, scan, handle derivation, precedence, cache, devMode mtime check, Clear Caches option, build counter)
      Rules: BR-11 to BR-14, BR-33 · Verify: TS-13, TN-8, TN-10
      *As built:* the plugin registers `index` through `ComponentLibrary::config()`, which copies
      `templateDirectories` and `sites` from `config/component-library.php`, so tests build their own
      `new Index([...])` over other roots. The API is `all()` (handle → `Component`, sorted),
      `get($handle)`, `roots()`, `invalidate()` (the Clear Caches action, a `TagDependency` over every
      site) and `reset()` (forgets the per-request memo and zeroes the test counters `builds` and
      `walks`). `Component` gained the location fields `path`, `root`, `stories`, `storiesPath`,
      `overrides` (the earlier root's file a later root replaced: the site-version badge), `duplicates`
      (TN-8's losers, for CL002) and `errors` (`path`, `line`, `message`: a tag or stories file that
      failed to parse, for CL001). A file whose tag doesn't parse is still indexed, under its derived
      handle, so one bad file never breaks the library. The cache key is site handle, schema version and
      the resolved roots. One walk per request records every file's mtime and size, and serves as both the
      devMode fingerprint and the rebuild's file list. Beyond BR-11: the `sites` folder is pruned from
      the other roots (the sandbox nests `_sites` inside its root), and a missing site folder is logged
      at info, because most sites have no versions. Beyond BR-13: a derived handle with no category
      (`loose.twig` → `@loose`) is indexed with an error, because it can't be included. A stories file
      that declares nothing, or doesn't parse, falls back to *Default*. Fixtures:
      `_sites/second/ui/good.twig`, and `tests/fixtures/edge/` (duplicates, a comment-only match, a loose
      file, a broken stories file), which is outside the sandbox config so B5 #4 stays clean. TS-13's
      50-include page needs 2.5's loader, so `IndexTest` stands in with 50 `get()` calls. 2.5's
      LoaderTest renders the real page and asserts the same counts. Legacy configs (BR-12 b) are 2.4's.
      A mutation run (cache never hit, sites not pruned, earlier root wins, later duplicate wins) failed
      5 of IndexTest's 22 tests.
- [x] **2.4 Legacy adapter**: `src/legacy/ConfigJsonAdapter.php` (render as a template in site mode; `variables` → props, `variants` → stories, `readme.md` → notes; placeholders)
      Rules: BR-15, BR-16 · Verify: Pest `LegacyTest`, TN-9
      *As built:* `ConfigJsonAdapter::read($root, $relative, ?$readme)` returns the same `Component` a tag
      does, and never throws. `Index::read()` calls it for a `.twig` with no tag and a sibling config
      (BR-12 b), only inside `build()`, so a warm lookup renders nothing (BR-33). The adapter switches to
      site mode itself as well. Craft refuses absolute template names and `..`, so a config under the
      site templates folder renders by its name there (site-root includes resolve, as in v1), and a root
      outside it (the sandbox fixtures) becomes the templates path for that one render. Both are
      `renderTemplate()` with no variables, and the mode and templates path are restored afterwards.
      v1's variable types map `string`, `text`/`textarea`, `number`, `checkbox`/`bool`/`boolean`/`lightswitch`, `json`, and `select` with
      `[{value, label}]`, a list or a hash. Anything else is `string`, as v1's text box was, and a
      `select` without options is `string` too. Defaults come from the base `context`. Context keys
      with no variable aren't props, but stay in every story's props. Legacy declarations are taken as
      they are, never refused. No variants gives one *Default* story holding the whole context. An
      unnamed variant is *Variant n*, and a repeated name gets ` (2)`. v1 variant `handle`s (the
      `--variant.twig` files) aren't indexed: none of mw-core's or webdna's 227 configs has variants.
      A stories file beside a legacy component still wins over its variants. The folder's `readme.md`
      is the notes, as Markdown, never Twig-rendered (v1 rendered it with `renderString`). With a tag
      and a config on one file, the tag wins, the config's handle stands in while the tag has none (so
      converting doesn't change the include name), and CL007 is raised. Every problem now carries its
      BR-31 code: `Component::$errors` holds CL001 and CL005, and the new `Component::$warnings` holds
      CL006 and CL007. `Component` also gained `configPath`. CL006 gives the placeholder's line when
      it's written literally. Fixtures: `templates/legacy/button/` (clean: variants, variables of every
      type, `{include:@ui:good}`, a readme, a legacy handle `@ui:legacy-button` beating the derived
      `@legacy:button`). `edge/legacy/`: `broken` (TN-9), `not-json`, `placeholders` (`{ref:}`,
      `{entry:}`, `{asset:}`, an invalid legacy handle, variant naming), `converted` (tag + config),
      `orphan` (a config with no template), and `site-path` (a site-root include). The `{ref:}`
      fixture is in `edge/`, not `templates/` as §7 first said, so B5 #4 can still show "0 problems".
      TN-9's preview panel is 3.1's, and the site page is 2.5's to show. Here, the broken config is
      listed with its error and line, and the rest of the root is indexed. A mutation run (adapter
      not switching to site mode, variant context not merged, placeholders never matched, the tag
      branch skipped) failed exactly the 4 matching tests of 124. A failure that throws broke the
      whole edge index (15 failures).
- [x] **2.5 Loader**: `src/twig/Loader.php`, wrapping Craft's loader for owned names only, on both the site and CP Twig instances
      Rules: BR-17, BR-18 · Verify: TN-5, TN-6, Pest `LoaderTest`
      *As built:* `ComponentLibrary` listens for `View::EVENT_AFTER_CREATE_TWIG` and wraps that one
      environment's own loader (`Loader::$inner`). Craft creates one environment per view and
      template mode, so this covers site and CP modes, and any other `View`, and no loader is ever
      replaced app-wide. `Loader::owns()` is `Component::HANDLE_PATTERN`. A name it owns resolves
      through `Index::get()` for Craft's current site. It never reaches Craft's loader, which also
      refuses paths outside the templates folder, so the loader reads the file itself. The cache key is
      the absolute path (as Craft's is), so a component compiles once whether it's included by handle
      or by path, and a site version compiles apart from the file it replaces. An unknown handle
      throws Craft's own `TemplateLoaderException` ("Unable to find the template “@ui:nope”."), a
      Twig `LoaderError`, so `ignore missing`, `include(…, ignore_missing = true)`,
      `source(…, ignore_missing = true)` and fallback lists all work. Beyond BR-17: the pattern
      gained the `D` modifier, because a bare `$` also matched `"@ui:good\n"`. `LoaderTest` renders
      through Craft's environments in both modes (include, `include()`, embed, extends, `source()`, a
      handle built at runtime), proves with a spy loader that owned names never reach Craft and that
      other names reach it unchanged, and compares `@ns/file.twig` (a registered `@clns` template root)
      and paths with Craft's bare `TemplateLoader`. It also renders the StoryTest fixtures' *In a
      toolbar* and *Custom panel* stories through Craft, and runs TS-13 on the real 50-include page
      (`templates/pages/fifty.twig`, copied to a temporary root under a swapped-in `Index`). A mutation
      run (unknown handles falling through to Craft, CP mode not wrapped, `isFresh` always true, the
      pattern without `D`) failed 11 of 178 tests, and every mutation failed at least one. CP
      dashboard, entries, plugins, clear-caches and library pages, and both sites' front pages, return
      200 as admin. B5 #4 waits for 5.1's check command.
- [x] **2.6 Resolver service**: `src/services/Resolver.php`, with the signature exactly as BR-19
      Rules: BR-19 · Verify: TN-17
      *As built:* the plugin's `resolver` component (`getResolver()`). It walks `Index::roots()`
      last first, which now takes an optional site handle, so the resolver and the index share
      BR-11's order. A given handle counts only once `getSiteByHandle()` returns a site, and only
      Craft's `$site->handle` becomes a path segment. An unknown handle returns `null` and never
      falls back to the base roots. Beyond BR-19: a path with a null byte or an empty path returns
      `null`, any `..` substring is refused (not only a `..` segment), and a root other than the
      site folder never reaches into the `sites` folder, so `_sites/<other>/…` can't read another
      site's version. The index gained a public `sitesFolder()` for that. With `sites` unset, a
      valid handle searches only the base roots. `ResolverTest` covers precedence in both root
      orders, fallback, the current site, TN-17's two paths plus `/ui/good.twig` (a leading `/`
      that normalises to a real file), bad handles and the sites-folder reach. A mutation run
      (no `..` check, no `/` check, first root wins, raw handle, no sites-folder rule) failed 1
      to 4 tests each, and every mutation failed at least one test. B5 #4 waits for 5.1's check command.
- [x] **3.1 Render action**: `src/controllers/RenderController.php`, `src/services/Renderer.php`, `src/templates/_render/{layout,error}.twig` (scope recheck, guest identity, prop coercion, headers, error scopes)
      Rules: BR-20 to BR-26 · Verify: TS-7, TS-10, TN-1 to TN-4, TN-16
      *As built:* the plugin's `renderer` component (`getRenderer()`, `layout` from the config file).
      For 3.2 and 4.2: `createToken($scope, ?$until)` (a token routed to `Renderer::ROUTE` holding
      only `scope`, one hour or `$until`, no usage limit) and `previewUrl($site, $token, $handle,
      ?$story, ?$props)`. The templates moved to **`src/templates/site/_render/{layout,page,error}.twig`**,
      registered as site template root `component-library` → `src/templates/site`, so they're named
      `component-library/_render/…`. Craft routes any public site template by its URL, template roots
      included, and checks for `_` only in the path below a root. v1's shape (the whole `templates`
      folder as the root) would have served the CP templates as front-end pages, and a root named
      `_component-library` doesn't stop it. `page.twig` extends the layout and fills `component` with
      the story's HTML, rendered first so a broken component fails before any layout output, and
      `viewClass` only when the component sets one (`clLayout`, `clHtml`, `clViewClass`, prefixed so
      they can't shadow a layout's variables). A stories file in a root outside the site templates
      folder renders with that root as the templates path, as the legacy adapter does. So a story
      body there reaches other components by handle only, which is how the sandbox fixtures are
      written. BR-22: the render reads no site from the request. The token row is read with its
      expiry by the controller itself, because Craft deletes expired tokens only once per process.
      Over HTTP, Craft's own 400 answers an unknown or expired token (TN-1), from
      `Application::init()`, and a token's route beats an `actions/` path. BR-24 details: a value
      that doesn't coerce is dropped and the story's value stands, as does an over-long string. Numbers
      in string props become strings, `"true"`/`"1"` become booleans, numeric strings become numbers,
      and a `select` takes the file's own option value. Every string inside a `json` value is escaped
      `Markup`, and a key that HTML-escaping would change is dropped. More than 8 KB, or anything but
      a JSON object, is 400. BR-16's `{include:@h}` renders the included handle's first story, as v1
      did, and only in values from files, nested 3 deep at most (which also stops cycles). BR-23:
      `setIdentity(null)`, then `resetGlobals()` on the site Twig environment, which caches
      `currentUser`. BR-26: unknown component or story is 404, and every status carries the four
      headers. The error panel's detail has the site templates, storage, plugin, vendor, project and
      root paths removed, and any other absolute folder elided. Beyond BR-21: a suspended user's
      previews stop. `RenderTest` (82 tests) covers TS-7, TS-10, TN-1 to TN-4 and BR-20 to BR-26 in
      process. craft-pest keeps one app, so a curl script covers what that can't show: TN-1's 400s,
      `second`'s real base URL, TN-16 with a real logged-in cookie (still logged in after), and the
      headers. A mutation run (16 mutations, among them strings unescaped, scope always valid,
      detail in every scope, scope from the query string, no size limit, undeclared props kept, no
      headers, and the whole templates folder as the site root) failed at least one test each. Only
      "identity kept" passes in process, and the curl TN-16 check fails on it.
- [x] **3.2 CP viewer**: `src/controllers/ViewerController.php`, `src/templates/viewer/*`, `src/web/assets/viewer/{ViewerAsset.php,viewer.js,viewer.css}` (tree, search, controls from props, stories, site switch, source, notes, URL state, empty states, test hooks)
      Rules: BR-2, BR-22, BR-34, BR-35 · Verify: TS-2, TS-3, TS-14, TN-15
      *As built:* one file more than planned, **`src/services/Viewer.php`** (the plugin's `viewer`
      component), so 4.2's share viewer reuses it with a `share:` token. `state($site, $handle, $story,
      $props, $token, $url)` returns everything the templates need, and the templates are partials
      (`viewer/_tree`, `_component`, `_empty`) that `viewer/index.twig` puts on the CP layout. Paths
      show only with `showPaths` (BR-29). The CP route is `component-library/<handle:@[A-Za-z0-9_:-]+>`,
      so no word like `shares` can be a handle. Every request value is checked against something the
      server holds: `site` against `getSiteByHandle()` (else the primary site), the handle against the
      index (else 404), the story against the component's stories (else its first), and `props` must
      decode as the renderer would and is cut to declared props. BR-22 in practice: a checked `site`
      picks the iframe's base URL **and whose index the tree and badge come from**, since AC-3 needs that
      site's own version. The index is read with that site set as current and restored afterwards. The
      badge (`data-cl-site-version`) marks a component whose root is that site's folder. Controls come
      from Craft's form macros: text, textarea, number, a native checkbox for `bool` (a lightswitch's
      jQuery change event never reaches a vanilla listener), select with a blank option unless required
      and set, and a JSON textarea. Tabs are the CP layout's own (`tabs`), so Craft handles their
      keyboard and narrow-screen menu. The tree is the layout's `sidebar`, which Craft puts behind
      *Show sidebar* on a narrow screen. The site switch is a GET form with a submit button, so arrow
      keys in the select don't navigate. `viewer.js` is a plain ES module with no CP globals: a change
      sets the iframe's `story` and `props` on the same token (no usage limit), replaces the address,
      and fills the site form's hidden fields. A setting changed by hand survives a story switch
      (TS-2 step 3's copied address has both). **Trap:** Craft's `cpUrl()` adds the requested site to
      any CP URL without `site`, so a base URL with paths appended broke every link after a site switch.
      Links now pass `site` themselves, through a URL closure from the controller, and the GET form's
      action is split into path and hidden fields (`Viewer::formFor()`). craft-pest caches
      `Cp::requestedSite()`, so `ViewerTest` sets it by reflection. Also: craft-pest's one View
      prints an asset bundle only on the first page that registers it, so the test reads it at
      `EVENT_END_PAGE`. `ViewerTest` (31 tests) and AccessTest's action-path refusal cover the server
      side. A mutation run (12 mutations: site from any handle, site ignored, undeclared props kept,
      switch on one site, no badge, index not per site, site not restored, unknown handle → first,
      unknown story → Default, token for admin, no view gate, state attribute unescaped) failed at
      least one test each. Browser (sandbox, Chrome): TS-2 all steps, preview updated ~900 ms after
      typing (250 ms debounce). TS-3 steps 1-2. At 375 px, no horizontal page scroll and the tree behind
      the toggle. The focus order is search, tree, tabs, site, width, preview, settings, with no
      positive tabindex, every control named, and a visible ring on each (the iframe needed one, so it's
      on its frame). The window was in the background, so focus order came from a DOM audit, not real
      Tab presses: **Sam to confirm TS-14 by hand.** TS-3 step 3 (mw-core) waits for 6.2.
- [x] **3.3 Preview workspace** (added v0.16, built next, before 5.2): one workspace partial used by
      both viewers (`src/templates/viewer/`, `src/templates/share/index.twig`). The CP page leaves
      `_layouts/cp` for a bare CP page, and the controllers are unchanged. `viewer.js` takes over the tabs
      and sidebar toggle from `share.js` and adds the devices, scale-to-fit, divider and `cl.`
      storage. `viewer.css` holds the workspace and the bezel. `Viewer::state()` and the address
      carry `device` and `orientation`. `ViewerTest` and `ShareViewerTest` hooks are updated.
      Rules: BR-34, BR-37, BR-38, BR-39, BR-40 · Verify: TS-15, TN-18, TN-19, TS-14, TS-2 and TS-3
      steps 1–2 re-run in both viewers, B5 #1-3
      *As built:* three changes from Sam during the build (v0.17). The controls column became v1's
      **details drawer**: a row of tabs under the preview that opens upward, with the divider on
      top of it. **Previews are always 100%**: no scale-to-fit, and a bigger device scrolls in the
      stage. The **site select switches on change**: its button is hidden by script and shows
      only without it (3.2 had the button so that arrowing through a closed select wouldn't
      navigate, and that trade-off is now Sam's). The page is `viewer/_workspace.twig` (header,
      tree, body), embedded by the CP `viewer/index.twig` on `_layouts/basecp` (Craft's CP styles
      and script, no nav) and by `share/index.twig`. `viewer/_component.twig` holds the preview,
      divider and drawer. The tabs are `_includes/tabs` under the id `cl-tabs`, because Craft's CP
      script binds `#tabs` only. `share.js` is deleted. `Viewer::device()` settles `device` and
      `orientation` (anything unlisted is desktop portrait, and desktop is never turned), and
      `deviceParams()` puts them in tree links and the site form, never the preview URL.
      **Trap:** Craft's CP layouts set a Twig variable `orientation` (the text direction, `ltr`),
      which overwrote the state's, so the state's key is `deviceOrientation`. The icons come from
      `iconSvg()`, which exists on the share page because it renders in CP template mode. The bezel
      art is Craft's published `cp/dist/images/preview` SVGs, passed as CSS custom properties.
      `ViewerTest` (+14) and `ShareViewerTest` (+10) cover TS-15 steps 1, 4 and 5 and TN-18, with a
      shared `unusable devices` dataset in `tests/Pest.php`. A mutation run (7 mutations: any
      device accepted, desktop turned, links drop the device, device sent to the preview, the
      `orientation` clash, no device group, the site form's device sent on desktop) failed at least
      one test each. Browser (sandbox, Chrome), in both viewers: phone 375×667 and landscape
      667×375 at 100%, and tablet 768×1024 scrolling with its top in view. Rotate is off on Desktop.
      The address restores device, orientation, story and props, and tree links keep the device.
      The drawer opens at 40%, clamps at 120 px and at a 200 px preview, closes from the open tab,
      and is remembered. The tree state is kept. Garbage `cl.` values give defaults with no script
      error (TN-19). Refresh reloads the frame, and Open matches it. The site switch keeps the view.
      At 375 px there's no sideways scroll, and the tree is behind its toggle. `window.Craft` is
      undefined on the share page. The Chrome window was backgrounded, so resize-driven layout was
      read after a forced render, and **TS-14's keyboard pass is still Sam's**.
- [x] **3.4 Preview background** (added v0.21): the `background` tag key in `src/twig/ComponentTokenParser.php` and `src/models/Component.php`; `bg` and the canvas wrapper in `src/services/Renderer.php` and `src/templates/site/_render/*`; the new `src/web/assets/preview/dist/preview.css`, published and linked from the render; the *Background* group in `src/templates/viewer/_workspace.twig`, `src/services/Viewer.php` and `src/web/assets/viewer/viewer.js` (address, iframe URL, tree links without `bg`); fixture `tests/fixtures/edge/ui/on-dark.twig`; `docs/format.md` (the key) and `docs/setup.md` §3 (the switch, and that it overrides only `html` and `body`)
      Rules: BR-5, BR-20, BR-41, BR-42 · Verify: TS-16, TN-20, B5 #1-4
      *As built (29 Sep 2026, v0.22):* B5 #1-4 green (PHPStan OK, ECS OK, Pest 505 passed, check =
      the one bad-tag CL001). `Renderer::background()` is the one place a request `bg` is read:
      it returns a `Component::BACKGROUNDS` constant, else the tag's `background`, else `site`.
      **Spec change:** v0.21's BR-42 read `bg=site` as "the component's own", which left *Site*
      unable to show the site background on a `dark` component. `site` is now a choice, and the
      address and preview carry `bg` only when it differs from the component's own, which for
      `good` is exactly TS-16 as written. The toolbar lives in `viewer/_component.twig` (3.3 moved
      it out of `_workspace.twig`). `preview.css` is published by `Renderer::previewCssUrl()`,
      not an asset bundle, since the layout's head isn't the plugin's. `Index` has a `FORMAT`
      constant in its cache key, because a cached `Component` from before the new property would
      unserialize with it uninitialised. The toolbar chip class is `cl-chip`, not `cl-swatch`:
      TN-18 proves the page never echoes `watch`. `BackgroundTest` plus rows in TagTest
      and CheckTest. 16 mutations each caught. HTTP: no `bg`, `bg=site` and `bg=purple` renders
      are byte-identical (`cmp`) to the render captured before the change, 607 bytes. Browser
      (sandbox, Chrome), in both viewers: *Light* whitened the page in 92 ms with the button
      unchanged, `aria-pressed` and the tab stop followed, `bg` reached the address, the iframe
      and *Open*. The arrow keys moved within the group. A story switch kept dark, the tree links
      carried no `bg`, and `@ui:nested` opened on *Site*. A copied `bg=light` address reopened
      light. *Site* took `bg` out. The share page did the same with `window.Craft` undefined.
      *Changed (29 Sep 2026, v0.23, Sam's request):* the three buttons are now one *Mode*
      dropdown (`select#cl-background[data-cl-background]`), laid out like the site switch. A
      change reloads the preview. The swatch chips and the arrow-key group are gone. Pest 505
      passed; Chrome, CP viewer on `good`: *Light* and *Dark* set `bg` on the iframe and the
      address and the canvas, and *Site* took all three out.
- [x] **4.1 Share service and management**: `src/services/Shares.php`, `src/controllers/SharesController.php`, `src/templates/shares/*`, GC hook, user-delete cascade
      Rules: BR-3, BR-27, BR-28, BR-30, BR-36 · Verify: TS-4, TN-11 to TN-13
      *As built:* validation lives in a form model, `src/models/ShareForm.php`. The expiry is a native
      date input (`Y-m-d`), and "today" is the UTC day, since a link ends at 23:59:59 UTC on its day
      (§4). The list shows expiry in UTC for the same reason. Only the list page pre-fills the two
      weeks: a post with no date is refused, never defaulted. The new address rides a session flash
      that the list reads and deletes in one go (`data-cl-share-url` on its panel). *Cancel link* is
      Craft's `formsubmit` with `data-confirm`, on active rows only. Cancelling again never moves
      `revokedAt`, an unknown id is 404, and a link both cancelled and expired shows *Cancelled*.
      **Trap:** Craft soft-deletes users, so the table's `ON DELETE CASCADE` fires only when a user is
      purged. A `User::EVENT_AFTER_DELETE` handler deletes the links at once (TN-13). GC is a
      `Gc::EVENT_RUN` handler. `Renderer::scopeIsValid()` now asks `Shares::active()`, so "active" has
      one definition. For 4.2 the service also has `find($token)` (hash lookup, `null` for any
      malformed token), `status()`, `markUsed()` (once a minute) and `url($token)`.
      `SharesTest` (29 tests) covers TS-4 steps 1-4, TN-12, TN-13, BR-30 (no mail, no queue job) and
      BR-36 (the exact column list). TN-11 stays in `AccessTest`. A mutation run (19 mutations) failed
      at least one test each, except two equivalent ones: a property default for `expiry` (the
      controller always sets it) and a lookup by the raw value (the token pattern refuses a hash
      first). Browser (sandbox, Chrome, admin): TS-4 steps 1, 3 and 4, then *Cancel link* through
      Craft's `formsubmit` (confirm stubbed) set `revokedAt` and removed the button. Step 2's +91 is
      refused by the date input's `max` in the browser and by the server in Pest.
- [x] **4.2 Share viewer**: `src/controllers/ShareViewerController.php` (site URL rule), share layout reusing the viewer templates with the CP stylesheet, and the expired, cancelled and unknown pages. Settle Appendix A row 2 first.
      Rules: BR-26, BR-27, BR-29 · Verify: TS-5, TS-6
      *As built:* Appendix A row 2 is resolved: CP stylesheets only, through a new
      `src/web/assets/share/CpStylesAsset.php`. `ShareAsset` (same folder, with `share.js` and
      `share.css`) lists the viewer's own `viewer.js`/`viewer.css` rather than depending on
      `ViewerAsset`, which keeps `CpAsset`. `share.js` does what Craft's CP script does for the CP
      viewer: tabs (click, arrow keys, Home and End) and the narrow-screen sidebar toggle
      (`body.showing-sidebar`). The templates are CP-mode `src/templates/share/{index,_message}.twig`,
      rendered on a site request with the CP layout's ids and classes, so `cp.css` lays them out.
      Neither is a front-end URL (only `templates/site` is a site root). Site rules
      `component-library/share/<shareToken>[/<handle>]` take any segment, so every refusal is the
      plugin's own page with its headers, never the site's 404. The param isn't `token`, because Yii
      copies route params into the query string, where Craft reads `token` as its own. Every share
      response sends `Referrer-Policy: no-referrer`, plus `Cache-Control: no-store` (a cancelled link
      mustn't come back from a cache) and `X-Robots-Tag: noindex, nofollow`. Unknown token, of any
      shape: 404, no state. Expired or cancelled: 410 with `data-cl-share-state`, cancelled winning,
      and no `lastUsedAt` touch. An unknown component inside an active link: 404 with a way back.
      An active visit calls `markUsed()`, and previews run under `share:<id>` tokens capped at the
      link's expiry. Links and the site form stay on share URLs (`Shares::url()` + handle). The
      header is "{label} · expires {date}", with the date in UTC like the share list. `_empty` shows
      only "Nothing to show yet." without `showPaths` (§3 first run), and the format-guide link moved
      inside the CP branch. `ShareViewerTest` (28 tests) covers TS-5 and TS-6 in process. A mutation
      run (15 mutations: paths shown, no referrer header, user-scope token, token not capped,
      cancelled or expired served, expired as 404, no `markUsed`, unknown handle → first, CP links,
      site ignored, `CpAsset` on the share page, empty state with folders, no state hook, no label)
      failed at least one test each. Real HTTP with no cookies (`$CLAUDE_JOB_DIR/tmp/smoke-4-2.sh`):
      share page 200 with no-referrer, preview 200 with no-store, noindex and no-referrer. After
      cancelling, the open preview is 403 "Preview expired" and the page is 410 `cancelled`. Unknown
      and malformed tokens are 404. Browser (sandbox, Chrome): CP look confirmed. A setting updated
      the preview and the address, examples and tabs switched, and arrow keys moved between tabs.
      At 375 px there's no horizontal scroll and the toggle shows the tree. `window.Craft` is
      undefined, and no path appears. Sam still reviews the wording (Appendix A row 1).
- [x] **5.1 Check command**: `src/console/controllers/CheckController.php`
      Rules: BR-31 · Verify: TS-11, B5 #4
      *As built:* the check invalidates the index and builds it fresh for every site, so it reports
      what's on disk, never a cache. Handles count as known if any site has one, since a template may
      include a site-only component. Each problem prints once, however many sites share it. It
      takes CL001, CL002 (from `duplicates`), CL005, CL006 and CL007 from the index, then parses
      every template (Craft's `defaultTemplateExtensions`) under `@templates` and every root once,
      with the site Twig. Literal `include`/`embed`/`extends` and `include()` give CL003, or CL004
      inside a `.stories.twig`. A reference marked `ignore missing` is never reported (BR-17).
      CL008 fires only when a name built at run time starts like a handle (`'@blocks:' ~ x`,
      `"@blocks:#{…}"`). Plain dynamic paths such as mw-core's `"_sites/#{…}/head.twig"` aren't
      the library's business, and would otherwise fail every `--strict` run. A template that
      doesn't parse and isn't a component is skipped with an info log line. Paths print relative to
      `@templates`, else to `@root`, and a problem with no line (CL002, a loose handle) prints the
      path without `:line`. Only index winners are checked: a component overridden by a later root
      on every site isn't reported. **B5 #4 amended:** the sandbox root keeps the deliberate
      `ui/bad-tag.twig`, which the viewer tests need, so B5 #4 expects exactly that one CL001 and
      exit 1. The 0-problems case is TS-11 step 2. New fixtures are `edge/pages/{references,extended}.twig`
      and `edge/refs/panel{,.stories}.twig`. `CheckTest` (31 tests) covers TS-11 and BR-31.
      A mutation run (19 mutations: `ignore missing` unchecked for includes or embeds, CL008 on any
      name, `--strict` ignored, always exit 0, cached index used, no dedupe, current site only, site
      not restored, embeds, extends or `include()` not scanned, stories as CL003, absolute paths, no
      CL002, warnings dropped, no count line, known handles ignored, roots not scanned) failed at
      least one test each. Three were missed at first and closed by test fixes: warm every site's
      index before the stale-cache test, start the site test on the primary site, and add a root
      outside `@templates`. B5: PHPStan OK, ECS OK, Pest 409 passed, and B5 #4 as amended.
- [x] **5.2 Make command**: `src/console/controllers/MakeController.php`
      Rules: BR-32 · Verify: TS-12
      *As built:* `<category>/<name>` must be exactly two segments matching
      `MakeController::SEGMENT_PATTERN` (`/^[a-z0-9-]+$/D`). The template's tag holds only `name`
      (the name with hyphens as spaces, first letter capitalised: `icon-button` → *Icon button*) and
      `status: 'wip'`, over `<div class="<name>"></div>`. The stories file holds one empty *Default*
      story. `--root=n` counts from 1 over `templateDirectories` alone. A site folder is left out,
      because a site version overrides something that already exists, so it's copied rather than
      made. If either file exists, nothing is written (exit 73). Making `ui/badge` flat beside
      `ui/badge/badge.twig`, or the reverse, in the same root is also refused, since both claim
      `@ui:badge` (CL002). Making it in a later root is allowed: that's an override (BR-13). A bad
      path or root exits 64. The command invalidates the index, so the next page load sees the
      component even when devMode is off and the cache isn't checked against the disk. Printed paths
      use the check's `CheckController::display()`, now public static. `MakeTest` has 31 tests,
      covering TS-12 steps 1-3 and BR-32. A mutation run (20 mutations: no invalidate, overwrites,
      only one file checked, any segment count, pattern unanchored or without `D`, segments
      unchecked, root 0-based, off by one or unvalidated, site folder counted, `--folder` ignored,
      no clash check or clash across roots, writes before checking, status, name, story, absolute
      paths, exit 0 on refusal) failed at least one test each. Real CLI in the sandbox: make,
      refuse, bad path, bad root, `--folder`, `--root=1 --folder=0`. Then over HTTP, the CP viewer
      showed the new component (tree 7 → 8, one Default story) on the next load after a CLI make.
      The probe files were removed afterwards. B5: PHPStan OK, ECS OK, Pest 468 passed, and B5 #4
      as amended.
- [x] **5.3 Docs**: `README.md`, `docs/{setup,format,share-links,upgrading-from-v1,resolver}.md`, a `CHANGELOG.md` 2.0.0 entry. The upgrade guide leads with `'legacy' => true` (BR-12), and the setup guide says new sites leave it off.
      Rules: BR-4, BR-12, BR-19 · Verify: Sam reads the upgrade guide against TS-9's steps
      *As built:* the upgrade guide's steps are TS-9 step 2's three changes (the `config/app.php`
      removal, the plugin install, `'legacy' => true`) plus the Composer bump from `^1.0@beta` to
      `^2.0` and the permission grant, and its check step is TS-9 step 4. It also lists what v1 had
      that v2 drops: the front-end pages, `COMPONENT_LIBRARY_VIEW_KEY`, the `navigation` key (mw-core
      sets it, and v2 ignores it), Formatters, the `{ref:}` family, and Twig-rendered readmes. The
      CHANGELOG entry is dated *Unreleased*, since tagging is out of bounds (B6). The v1 leftover
      `src/README.md` (the `config/app.php` module snippet BR-4 now refuses) is deleted. Each doc's
      claims were checked against the code, which corrected three drafts: `bool` is a checkbox, a
      refused value keeps the story's value rather than being truncated, and the tree groups by the
      handle minus its last part. The README's and the format guide's Twig examples were indexed
      in the sandbox with a real `Index`, and all three files parse with no errors and the expected
      stories and inferred types. `Viewer::FORMAT_GUIDE` resolves once pushed to `main`. Sam's read
      against TS-9 is still to do. B5: PHPStan OK, ECS OK, Pest 468 passed, and B5 #4 as amended.
- [ ] **6.1 LLL install and pilot** (LLL repo, own branch): `config/component-library.php`, `templates/_component-library/preview.twig` (the craft-vite pair from `_layouts/public.twig:38-40`), convert `_components/ui/button.twig`, `form/text.twig` and `ui/dialog.twig`, and add their `.stories.twig`
      Rules: BR-7, BR-8, BR-25 · Verify: TS-8, B5 #5
      *Staged, awaiting Sam's approval on craft5 (29 Sep 2026).* Sam's decision: v2 is not
      installed or run in LLL until he has reviewed the pilot in the sandbox and approved it. So
      the LLL work sits, unchecked-out, on LLL branch `feature/component-library-v2` (from
      `staging`, commit 23e07e24). A first install there was rolled back: plugin uninstalled, the
      checkout back on `staging`, the plugin copy removed, and `/apply` 200. For review, the same
      six component and story files run in the sandbox. They sit in `lll-pilot/_components`, a
      root outside `templates/` because the check scans `templates/` whole. The sandbox also has
      the ten `_components/icon/` templates and nine `src/static/icons/` SVGs the button's options
      name, at LLL's own paths, so the files are byte-identical to the branch. It has LLL's
      staging build in `web/dist`, built into the container's `/tmp`, so LLL's own `web/dist` is
      untouched. `templates/_lll/preview.twig` stands in for LLL's craft-vite layout, with the
      two hashed tags written in. The sandbox `config/component-library.php` adds the root and
      layout by hand, except under Pest (`class_exists(\Pest\TestSuite::class, false)`), whose
      tests assert on the fixture roots and the bare layout. A `setup.sh` rerun drops them. Fixture
      previews also render in the LLL layout for now. After approval: check out the branch,
      `composer install`, `plugin/install`, and redo TS-8 step 1 and B5 #5 in LLL.
      On the branch, the plugin is unreleased, so LLL installs it as the sandbox does: a `plugins/*` path repository
      and `"webdna/component-library": "@dev"`. The copy in `plugins/component-library` is kept out
      of git by `.git/info/exclude`. At release, the branch swaps it for `^2.0`. The preview layout
      adds an `x-data` root around the blocks, since the dialog's `x-teleport` needs an Alpine
      scope. Each tag sits after the file's doc comment, which stays. Twig eats the newline after
      both, so the output is unchanged. The pilot found one hole the spec hadn't named, now in BR-24
      and the format guide: escaping protects HTML only. So every prop that LLL uses as script, a
      URL, a file read or an include is either a `select` (button `href`, `icon`, `iconTemplate`) or
      set only in stories' `with` (the dialog's Alpine expressions and `id`, `attrs`, text's
      `errorModel` and `icon`). Each component's notes say which. The dialog's stories drive it from
      their own `x-data` flag, so Cancel, the backdrop and Escape close it and a button reopens it.
      TS-8 evidence, from the rolled-back install in LLL. Step 1: `/apply` (as guest and signed in),
      `/support`, `/account/settings`, `/account/passkeys` and `/profile` were saved before the
      install and again after the conversion. With tokens masked, 0 differing pages. Two runs
      before the install also gave 0, and so did a run after the install but before the conversion.
      Step 4 (B5 #5): `0 problems`, exit 0. Redo both after the approved install.
      From the sandbox. Step 2: the index holds `@form:text` (8 stories), `@ui:button` (11) and
      `@ui:dialog` (5), with no errors. All 24 stories render 200 inside LLL's CSS. In Chrome,
      Button (all 13 variants), Text field (the marketplace ring and message) and Dialog show LLL's
      styling on its black body. Step 3: *Custom panel* is open with the story's own panel and no
      `popover-modal` preset. Its iframe holds no other story's ids. Cancel closes it and the
      button reopens it. Hostile props (a `javascript:` href, an undeclared `attrs`, a dialog
      `close` expression, an `iconTemplate` of another template) leave no trace in the render. The
      declared `title` arrives escaped. B5 #1-4 stay green with the pilot in place: PHPStan OK,
      ECS OK, Pest 468 passed, and the check's only problem is still bad-tag's CL001.
      Trap: in the automation Chrome tab (backgrounded, so `requestAnimationFrame` never fires),
      Alpine's enter and leave transitions hang, and the dialog seems not to close. Take a
      screenshot between steps to force frames. In LLL itself, previews are unstyled unless its
      Vite dev server (`ddev exec npm run dev`) is running, since its dev environment always uses it.
- [ ] **6.2 Consumer back-compat proof** (mw-core and webdna on throwaway local branches, never committed): `$CLAUDE_JOB_DIR/tmp/compat.sh` fetch-and-diff script
      Rules: BR-15, BR-17, BR-25 · Verify: TS-9, B5 #6

---

# Appendix A: Open questions

| # | Question | Owner | State |
|---|---|---|---|
| 1 | Share-link wording: the viewer header, the expired, cancelled and unknown pages, and the one-time URL notice | Sam | Open, blocking release |
| 2 | Does Craft's CP stylesheet (`CpAsset`) load and style correctly on a site request, without the CP JS globals? If not, the share viewer ships a copied subset of CP CSS. | Developer | **Resolved in 4.2:** the stylesheets style a site page fully, with no CP JS and no copied CSS. `CpAsset` itself isn't used: it brings the CP's jQuery and Garnish stack and puts the visitor's email and user id in `window.Craft`. `CpStylesAsset` publishes Craft's own three stylesheets (the CP theme by name, since `ThemeAsset` picks the front-end theme on a site request), and the share page supplies its own tabs and sidebar toggle. Promoted into §6 *Screens*. |
| 3 | Does `markhuot/craft-pest-core` run a plugin's `tests/` from the host project, as B5 #3 assumes? If not, the tests run from the sandbox's own `tests/` and the profile is updated. | Developer | **Resolved in 1.2:** yes, from the sandbox root, but only with `-c plugins/component-library/phpunit.xml --test-directory=plugins/component-library/tests`. Without `--test-directory` the tests run with Craft booted but without `tests/Pest.php`. Promoted into §7 *Automated checks* and B5 #3. |

**Assumptions**
- Previews render as a guest, so components that need a logged-in user (several of LLL's feed and
  message components) show their guest state or the error panel. That's accepted, not fixed.
- Share viewers may see component source. It's developer-authored and shows no paths.
- Deleting a user should delete their links. That's safer than orphaned working links.
- A Craft token's route params are server-side (in the `tokens` table) and can't be altered by the
  client.
- mw-core's and webdna's viewer layouts use only v1's `component` and `viewClass` blocks. TS-9
  step 4 exposes it if not.
- The viewer needs no JS build step. Vanilla ES modules and CP Garnish are enough.
- Nobody outside the team links to v1's front-end `/component-library` URLs.
- Off `_layouts/cp`, the CP viewer loses the CP's session-expiry warning and its nav. Someone
  whose session lapses finds out on their next page load, which is acceptable for a read-only
  viewer. The CP stays one click away through the header link.

---

# Appendix B: build scaffolding

## B1. Before writing anything

Read `docs/specs/_PROFILE.component-library.md` in full, especially §2 (rsync before every check)
and §5 Traps. The agreed boundary is `docs/specs/_scope/component-library-v2.md`. Build on a branch
cut from `spec-component-library-v2`. Commit, never push.

## B2. Context to load

Task 1.1 deleted the v1 files named in items 2-5. Read them from history:
`git show 8c32afa:<path>`.

1. `git show 5824487`: the four v1 holes and the shape of each patch. v2 makes each one
   structurally impossible rather than re-patching it.
2. `src/ComponentLibrary.php:43` (the app-wide loader swap task 2.5 must not repeat) and
   `src/twig/ComponentLibraryLoader.php:72-97` (the `@handle` resolution behaviour to keep).
3. `src/base/Formatters.php:99-151`: the map build. Note the dead cache (101-103), the root order,
   and the variant handle → `--variant.twig` mapping.
4. `src/helpers/ComponentViewerHelper.php:20-27, 141-157, 303-370`: config rendering, handle
   grouping, placeholder parsing (what BR-15 and BR-16 keep and drop).
5. `src/templates/render.twig`, `render.example.twig`: the `component` and `viewClass` block
   contract consumer layouts depend on.
6. `~/Projects/mw-core/config/component-library.php` and
   `templates/_base/components/button/button.config.json`: a real Twig-wrapped config with
   `variables`.
7. `~/Projects/mw-core/modules/mwcore/services/HandlebarsService.php` (`resolve`, `getPartials`):
   the consumer BR-19 is shaped for.
8. `~/Projects/lll/templates/_components/ui/dialog.twig:52-172`: blocks `panel`, `actions`,
   `extra`, and Alpine expressions as props. Also `ui/button.twig:1-83` (`import _self`) and
   `form/text.twig:6-54`.
9. `~/Projects/lll/templates/profile/company.twig:298-310`: a real embed overriding `panel`, the
   model for the *Custom panel* story.
10. For 3.3: Craft's entry preview, whose source ships only inside sourcemaps. `sourcesContent` of
   `./js/Preview.js` in `vendor/craftcms/cms/src/web/assets/cp/dist/cp.js.map` (`updateDevicePreview`
   is the scale-to-fit, `deviceMaskDimensions` the bezel sizes), and `./css/_preview.scss` in
   `cp/dist/css/cp.css.map`. The bezel art is `cp/dist/images/preview/*.svg`, which `CpStylesAsset`
   already publishes. Then 3.2's and 4.2's as-built notes, for what the two viewers share today.

## B3. Guardrails

- **Do not call `renderString()` on anything.** v1's injection came from building Twig source out of data.
- **Do not replace Craft's Twig loader.** Wrap it and delegate everything outside BR-17's regex. An
  over-broad match breaks CP and plugin templates for every user.
- **Do not read a site, path or template name from the request.** That is exactly how the `?site=`
  traversal happened.
- **Do not store or log a share token, or compare one with `==`.** A plaintext token in a table or a
  log is a working link for whoever reads it.
- **Do not let a request string reach a component except as escaped `Markup`.** LLL's dialog `body`
  is `|raw`, so one unescaped path is reflected XSS on a live storefront domain.
- **Do not use `logout()` or `switchIdentity()` to render as a guest.** Either one signs the visitor
  out of that front end.
- **Do not build the legacy index in CP template mode.** Site-root config paths don't resolve there,
  so every legacy component would index as broken.
- **Do not make the `component` tag emit anything.** BR-7 is what lets LLL convert heavily used
  components without a regression pass.
- **Do not reach for `Craft.Preview`, Garnish, jQuery or Craft's `lp-*` classes in the workspace.**
  The share page has none of them, by design (§6 *Screens*), so a viewer that needs them breaks for
  every client. The `lp-*` panes are also fixed-position overlays that can change in any Craft release.
- **Do not send `device` or `orientation` to the render.** The render URL only ever carries
  `component`, `story`, `props` and `bg` (BR-20). A new request value there is a new hostile input.
  `bg` is allowed because it's a closed set: the render compares it to `site`, `light` and `dark`
  and otherwise ignores it. Never build a class, attribute, path or template name from its text (BR-42).

## B4. Definition of done

- [ ] Every AC in §7 passes. The manual evidence for TS-3 step 3, TS-8, TS-9 and TS-14 is recorded
      in the task commit or the build-progress memory.
- [ ] Every BR in §5 is enforced in code, with a Pest test or a named manual scenario
- [ ] TN-1 to TN-20 behave as specified, and the §7 regression checks pass
- [ ] Every §7 test hook is present
- [ ] B5 runs clean
- [ ] Appendix A rows 2 and 3 are resolved and promoted into the body. Row 1 is reviewed by Sam
      before release.

## B5. Verification

```bash
# 0. Sync the working copy into the sandbox (always first)
rsync -a --delete --exclude .git --exclude vendor ./ ~/projects/craft5/plugins/component-library/

# 1. Static analysis
cd ~/projects/craft5 && ddev exec vendor/bin/phpstan analyse -c plugins/component-library/phpstan.neon --no-progress --memory-limit=1G
# expect: [OK] No errors

# 2. Coding standard
cd ~/projects/craft5 && ddev exec vendor/bin/ecs check --config plugins/component-library/ecs.php
# expect: [OK] No errors found

# 3. Tests
cd ~/projects/craft5 && ddev exec vendor/bin/pest -c plugins/component-library/phpunit.xml --test-directory=plugins/component-library/tests
# expect: "Tests: N passed", nothing failed or risky (Pest 2 exits 0 on risky, so read the line)

# 4. Library check on the sandbox fixture config
cd ~/projects/craft5 && ddev craft component-library/check
# expect: exactly one problem, "CL001 plugins/component-library/tests/fixtures/templates/ui/bad-tag.twig:3 …",
# then "1 problems", exit 1. bad-tag stays in the sandbox root for the viewer's error panel (TS-2);
# every other case lives in edge/. TS-11 step 2 proves "0 problems", exit 0 on a clean library.

# 5. LLL pilot
cd ~/Projects/lll && ddev craft component-library/check
# expect: last line "0 problems"

# 6. Consumer back-compat (TS-9)
bash $CLAUDE_JOB_DIR/tmp/compat.sh mw-core && bash $CLAUDE_JOB_DIR/tmp/compat.sh webdna
# expect: "0 differing pages" for each (CSRF tokens and asset hashes normalised)
```

| AC | Manual check |
|---|---|
| AC-2, AC-3 | TS-2 and TS-3 in a browser against the sandbox. TS-3 step 3 on mw-core. |
| AC-8 | TS-8 in LLL |
| AC-9 | TS-9 step 4 in the mw-core CP |
| AC-14 | TS-14, keyboard only and at 375 px |
| AC-15 | TS-15 steps 2-4 and 6-8 in a browser against the sandbox, in both viewers (steps 1 and 5 are Pest) |
| AC-16 | TS-16 steps 4-6 in a browser against the sandbox, in both viewers (steps 1-3 are Pest) |

## B6. Out of bounds

- mw-core and webdna: no commits. TS-9 uses throwaway local branches.
- LLL components other than the pilot three.
- Sandbox content, users and project config, except the fixtures from task 1.2. Snapshot first.
- The `security-patches-v1` branch. v1 stays as patched until each site upgrades.
- Pushing, tagging or publishing a release.

---

## Change log

| Date | Version | Change | By |
|---|---|---|---|
| 2026-09-28 | 0.1 | First draft, from the agreed scope note and design decisions | Claude, for Sam Birch |
| 2026-09-28 | 0.2 | Task 1.1 built. BR-1: view permission is Craft's `accessPlugin-component-library`, not `accessComponentLibrary` (Craft's own gate made a separate one unusable). B5: `ddev --dir` does not exist, commands now `cd` first; ECS takes `--config`. | Claude, for Sam Birch |
| 2026-09-28 | 0.3 | Task 1.2 built. Appendix A row 3 resolved: B5 #3 needs `-c` and `--test-directory`. `clViewers` fixture also holds `accessCp`. §7 fixtures table updated. | Claude, for Sam Birch |
| 2026-09-28 | 0.4 | Task 2.1 built. BR-6 applied also to the shape of the tag (unknown keys, types, statuses, options), and one tag per file. See 2.1's as-built note. | Claude, for Sam Birch |
| 2026-09-28 | 0.5 | Task 2.2 built. BR-8 applied also to where a story may sit: only at the top level of a `.stories.twig` file, never nested. A blank body counts as no body. See 2.2's as-built note. | Claude, for Sam Birch |
| 2026-09-28 | 0.6 | Task 2.3 built. BR-11: a missing site folder logs at info, and the `sites` folder is pruned from other roots. See 2.3's as-built note. | Claude, for Sam Birch |
| 2026-09-28 | 0.7 | Task 2.4 built. BR-15: the readme stays unrendered Markdown, and a root outside the site templates folder becomes the templates path for the render. Problems carry their BR-31 code. The `{ref:}` fixture moved to `edge/`. See 2.4's as-built note. | Claude, for Sam Birch |
| 2026-09-28 | 0.8 | Task 2.5 built. BR-17's pattern is anchored with `D` (a bare `$` matched a trailing newline). An unknown handle is Craft's `TemplateLoaderException`. See 2.5's as-built note. | Claude, for Sam Birch |
| 2026-09-28 | 0.9 | Task 2.6 built. BR-19 as specified. An unknown site handle returns `null`, and no root other than the site folder reaches into the `sites` folder. See 2.6's as-built note. | Claude, for Sam Birch |
| 2026-09-28 | 0.10 | Task 3.1 built. BR-21 also refuses a suspended user. The render templates live in `src/templates/site/_render/`, so no plugin template is a public front-end URL. §7 fixtures complete. See 3.1's as-built note. | Claude, for Sam Birch |
| 2026-09-28 | 0.11 | Task 3.2 built. BR-22: a checked `site` also chooses whose index the viewer lists, for AC-3's badge. The viewer's state lives in a new `Viewer` service for 4.2 to reuse. TS-14 focus order awaits a manual pass. See 3.2's as-built note. | Claude, for Sam Birch |
| 2026-09-28 | 0.12 | Legacy configs are opt-in (Sam's decision): BR-12 b and BR-15 apply only with `'legacy' => true` in the config file, default off. §1, journey 5, TS-9 step 2 and task 5.3 updated. The sandbox fixture config turns it on. | Claude, for Sam Birch |
| 2026-09-28 | 0.13 | Task 4.1 built. BR-28's "today" is the UTC day, matching §4's end-of-day-UTC expiry. §4's user-delete cascade needs an event handler, because Craft soft-deletes users. See 4.1's as-built note. | Claude, for Sam Birch |
| 2026-09-28 | 0.14 | Task 4.2 built. Appendix A row 2 resolved: the share viewer loads Craft's CP stylesheets without `CpAsset` or any CP JS (§6 *Screens*). Share pages also send `no-store` and `noindex`. See 4.2's as-built note. | Claude, for Sam Birch |
| 2026-09-28 | 0.15 | Task 5.1 built. BR-31 as specified: handles are known if any site has them, and CL008 covers only runtime names that start like a handle. B5 #4 now expects the one deliberate `bad-tag` CL001 and exit 1 (the 0-problems case is TS-11 step 2). §7 fixtures table updated. See 5.1's as-built note. | Claude, for Sam Birch |
| 2026-09-28 | 0.16 | Preview workspace added (Sam's request): both viewers become one full-screen workspace like Craft's entry preview, with a collapsible tree, a draggable split, and Desktop, Tablet and Phone with rotate and scale-to-fit. §1, vocabulary, §2, journey 1, BR-34 amended, new BR-37 to BR-40, §6 route and *Screens*, AC-15, TS-14, new TS-15, TN-18, TN-19, test hooks, regression row, new task 3.3, B2 item 10, two B3 guardrails, one assumption. | Claude, for Sam Birch |
| 2026-09-28 | 0.17 | Task 3.3 built, with three changes from Sam: the controls column is now v1's details drawer below the preview (a tab row that opens upward, the divider on top), tablet and phone always render at 100% and scroll rather than scale, and the site select switches on change. §1, journey 1, BR-34, BR-37, BR-38, BR-40, §6 *Screens* and *Design source*, AC-15, TS-14, TS-15 steps 1-3 and 6, test hooks. See 3.3's as-built note. | Claude, for Sam Birch |
| 2026-09-29 | 0.18 | Task 5.2 built. BR-32: roots are numbered from 1, a site folder isn't one, neither file is written if either exists, the other layout of a handle already in that root is refused, and the index is invalidated. See 5.2's as-built note. | Claude, for Sam Birch |
| 2026-09-29 | 0.19 | Task 5.3 built: README, five guides and the 2.0.0 CHANGELOG entry (dated *Unreleased*). The v1 leftover `src/README.md` is deleted. The upgrade guide awaits Sam's read against TS-9. See 5.3's as-built note. | Claude, for Sam Birch |
| 2026-09-29 | 0.20 | Task 6.1 staged, not ticked. It's built on LLL branch `feature/component-library-v2` (23e07e24), but by Sam's decision it isn't installed in LLL until he approves the pilot, which runs for review in the craft5 sandbox. BR-24 now says escaping protects HTML only: a prop used as script, a URL, a file path or a template name is a `select` or set only in stories, and the format guide says so too. TS-8 evidence is in 6.1's as-built note. | Claude, for Sam Birch |
| 2026-09-29 | 0.21 | Preview background added (Sam's request): BR-41 and BR-42, AC-16, TS-16, TN-20, task 3.4. The tag gains `background` (BR-5), and the render accepts a closed `bg=light|dark` (BR-20, §6, B3). v1 had no such switch (its `app.css` styled only the viewer), so the plugin's own `preview.css` is new. It sets only the page background. Container layout stays with `viewClass`, and the choice is not remembered per browser, so each component's default shows. | Claude, for Sam Birch |
| 2026-09-29 | 0.22 | Task 3.4 built. BR-42 amended: `bg` is read as exactly `site`, `light` or `dark`, so *Site* can show the site background on a component whose tag says `dark`. BR-20, BR-41, §6 and B3 now say `bg` is sent only when it differs from the component's own `background`. For a component on the default `site`, TS-16 is unchanged. See 3.4's as-built note. | Claude, for Sam Birch |
| 2026-09-29 | 0.23 | Sam's request: the background buttons become one *Mode* dropdown (BR-41, TS-16 step 4, the `data-cl-background` hook, `docs/setup.md`, CHANGELOG). Behaviour and `bg` are unchanged. The 6.1 install in LLL is on hold at Sam's word, even though the pilot is approved on craft5. | Claude, for Sam Birch |
| 2026-09-29 | 0.24 | Sam's request: the *Mode* option for `site` is labelled *Theme*, so it no longer reads as the site switch beside it (BR-41, BR-42, TS-16 step 4, `docs/setup.md`). The value stays `site` in the tag, `bg` and the address. | Claude, for Sam Birch |
