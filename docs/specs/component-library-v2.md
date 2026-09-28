---
spec: Component Library v2
slug: component-library-v2
status: draft
version: 0.7
date: 2026-09-28
author: Claude (for Sam Birch)
client: webdna (internal)
approver: Sam Birch
profile: _PROFILE.component-library.md
related: [_scope/component-library-v2.md]
---

# Component Library v2

> **Status:** draft · **Version:** 0.7 · **Profile:** `_PROFILE.component-library.md`
> The team browses and tries out every component in the control panel, previewed on each site's
> own styling. Clients review the same library through a link that expires and can be cancelled.

---

## 1. How it works

The team can open **Component Library** in the control panel and browse every component a site
has, by category or by search. Opening one shows it rendered with the chosen site's real styling,
next to its settings, examples, source and notes. Changing a setting, example or site updates the
preview in place. Every view has its own address to bookmark or paste into a ticket. Only people
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
keep working, and conversion is optional, one component at a time.

The idea it rests on: **the live site must not be able to tell v2 from v1, except that it's
faster.** Every existing way of referring to a component keeps working: short names, file paths,
and the per-site versions mw-core's eight storefronts rely on. The component list is built once
and reused. The four holes from the September review are designed out rather than patched:
typed values running as code, content disclosure, a site switch reaching the file system, and a
key that let anyone in when it was unset.

**Rejected alternatives.** A bespoke viewer design (the control panel's look is quicker and keeps
Craft's accessibility work). Applying the settings block's defaults on the live site (that makes
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
   2. See the preview, its settings, its examples, the source and the notes.
   3. Change a setting or pick an example. The preview updates without leaving the page.
   4. Switch site. The preview reloads in that site's styling. A site's own version is shown and marked.
   5. Copy the address. Anyone with the permission who opens it sees the same view.

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

**5. Upgrade a site from v1.** Swap the old module line for a plugin install, and grant the view
permission to the groups that should keep the library. Pages look exactly as before.

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
| BR-5 | A component declares itself with `{% component { … } %}` anywhere in its file. Keys: `name`, `handle`, `status` (`prototype`\|`wip`\|`ready`\|`deprecated`), `notes` (Markdown), `viewClass`, `props`. Each prop is a shorthand default (`label: 'Save'`, type inferred) or `{type, default, options, required, description}`. Types: `string`, `text`, `bool`, `number`, `select` (needs `options`), `json`. |
| BR-6 | The tag's argument must be a hash of literals, recursively (strings, numbers, booleans, null, arrays, hashes). A variable, filter, function call or concatenation is a Twig **syntax error at compile time**, naming the file and line. The library reads the tag by parsing, never by rendering. |
| BR-7 | The tag compiles to nothing. A converted component renders byte-identical output to the same file without the tag. Defaults feed the viewer only. |
| BR-8 | Stories live in `<name>.stories.twig` beside the component. `{% story 'Name' with { … } %}{% endstory %}` renders the component with those props. A body, if given, renders instead: any Twig, including `include`/`embed` of any component with block overrides. The current props are available to the body as `props`. Names are unique per file, and `with` follows BR-6. |
| BR-9 | Rendering one story executes only that story's body. A `.stories.twig` file is never indexed as a component and is rendered only by the preview. |
| BR-10 | No stories file means one story, *Default*, built from the prop defaults. A legacy config's `variants` become stories named by each variant's `name`, with its `context` merged over the base `context`. |

**Discovery, index and resolution**

| # | Rule |
|---|---|
| BR-11 | Roots are `templateDirectories` from `config/component-library.php` (default `['@templates/_components']`), in order. If `sites` is set, `<sites>/<currentSiteHandle>` is appended **last**. The site handle is always Craft's current site. A missing root is skipped with a log warning, and a missing site folder with an info line. The `sites` folder is never scanned as part of another root. |
| BR-12 | A component is either (a) a `.twig` file containing a `component` tag, pre-filtered by the regex `\{%-?\s*component\b` and then parsed, or (b) a legacy `<name>.config.json` with a sibling `<name>.twig`. If both describe one file, the tag wins and the check warns. |
| BR-13 | Handle: the tag's `handle`, else the legacy `handle`, else derived from the path relative to its root. Segments are joined with `:` and the extension dropped. A stem equal to its parent folder collapses (`ui/button.twig` → `@ui:button`, `components/button/button.twig` → `@components:button`). Across roots the **later root wins** per handle, which is how site versions work. Within a root, a duplicate keeps the first by sorted path, and the check reports it. |
| BR-14 | The index (handle → file, metadata, props, stories) is built at most once per site per cache lifetime. It sits in Craft's data cache, keyed by site handle and plugin schema version. It's cleared by a *Component library index* Clear Caches option and by `clear-caches/all`. With `devMode` on, it rebuilds when any file under a root is newer than the build (at most one stat walk per request). No include, in any mode, walks a directory. |
| BR-15 | Legacy `.config.json` files are **Twig templates** (`{{ raw({…}\|json_encode) }}`). They're rendered with `renderTemplate()` in site template mode, with an empty context, only during an index build, then JSON-decoded. A failure indexes the component with an error flag and doesn't throw. Keys read: `handle`, `name`, `status`, `context`, `variants`, `viewClass`, and `variables` (mapped to prop types: `"string"` → `string`, `{type:'select', options}` → `select`, and so on). A sibling `readme.md` becomes `notes`, as Markdown, unrendered. A config under the site templates folder renders by its name there. A root outside that folder becomes the templates path for the render, because Craft refuses other names. |
| BR-16 | In legacy defaults, `{include:@handle}` is honoured by rendering that handle with `renderTemplate()` at preview time (webdna uses it twice). `{ref:}`, `{entry:}` and `{asset:}` are **not** resolved. The value stays literal, and the check reports each one. |
| BR-17 | Names the plugin owns match `^@[A-Za-z0-9_-]+(:[A-Za-z0-9_-]+)+$`, and a variant's last segment may contain `--`. Every other name goes untouched to Craft's loader: Twig namespaces (`@ns/path`), plain paths, anything with `/`. An unknown owned handle raises Twig's standard missing-template error, so `ignore missing` works. This applies to `include`, `embed`, `extends`, `source()` and `include()`, in site and CP template modes. |
| BR-18 | Path includes of component files (LLL's `'_components/ui/button.twig'`) resolve through Craft as today. The index maps each file back to its handle, so the viewer lists it. |
| BR-19 | `resolver->resolve(string $path, ?string $siteHandle = null): ?string` returns the absolute path from the highest-precedence root (BR-11 order) containing the relative `$path`, or `null`. A path containing `..` or starting with `/` returns `null`. It's a documented, stable public API. |

**Preview rendering**

| # | Rule |
|---|---|
| BR-20 | The preview is an iframe on the target site's base URL, carrying a Craft token (`tokens->createToken`) routed to the render action. Token params hold only the scope, `user:<id>` or `share:<id>`. The lifetime is 1 hour or the share's remaining life, whichever is shorter, with no usage limit. The component, story and props travel as the query params `component`, `story`, `props`. |
| BR-21 | Every render request rechecks the scope: the user still exists and holds `accessPlugin-component-library`, or the share is active. On failure it returns 403, "Preview expired, reload the page". |
| BR-22 | The site rendered, and the index used, is the site the request was served on. No request value selects a site for rendering, indexing or a path. The viewer's `site` address parameter only chooses the iframe's base URL, and it's accepted only once `getSiteByHandle()` returns a site (otherwise the primary site). |
| BR-23 | Previews render as a guest. The identity is cleared in memory for the request only, with no session write, so `currentUser` is null whoever's browser it is. |
| BR-24 | `props` is a JSON object of at most 8 KB. Each key is coerced to its declared type: strings ≤ 2,000 chars, text ≤ 10,000, `select` must be one of `options` (else the default), `json` depth ≤ 5. Undeclared keys are dropped. **Every request-supplied string reaches the component as inert, pre-escaped text** (`Twig\Markup` of the HTML-escaped value), so `\|raw` cannot inject markup. It's never passed to `renderString`, placeholder-parsed or used in a path. HTML in props comes only from files. |
| BR-25 | The render extends the configured `layout`: a path, or a map of site handle → path, defaulting to the plugin's bare layout. It fills block `component`, and block `viewClass` when set. These are v1's block names, so mw-core's and webdna's layouts work unchanged. |
| BR-26 | Render responses send `Cache-Control: no-store`, `X-Robots-Tag: noindex`, `Referrer-Policy: no-referrer` and `Content-Security-Policy: frame-ancestors` (CP origin and primary site origin only). A render exception returns 500 with an error panel. In `user:` scope the panel shows the message, template name (relative to templates) and line. In `share:` scope it shows only "This component couldn't be shown." No absolute server path appears in any scope. |

**Share links**

| # | Rule |
|---|---|
| BR-27 | A token is 32 random bytes, base64url (43 chars), looked up by SHA-256 hash and shown once in the create response. The URL is `<primary site>/component-library/share/<token>`. Unknown: 404. Cancelled: 410, cancelled page. Expired: 410, expired page. Active means `revokedAt` is null and `expiresAt` is in the future. |
| BR-28 | The label is required, 1–100 chars, trimmed. The expiry is a date: default today + 14 days, minimum tomorrow, maximum today + 90 days, validated server-side. There's no limit on the number of links. |
| BR-29 | The share viewer offers everything the CP viewer does except share management. It shows source and notes, never file paths or roots, and sends `Referrer-Policy: no-referrer`. |
| BR-30 | Nothing is sent: no email, notification or webhook, for any event in this spec. |

**Tooling**

| # | Rule |
|---|---|
| BR-31 | `craft component-library/check [--strict]` prints `<code> <path relative to templates>:<line> <message>` per problem, then `<n> problems`. Errors: CL001 tag unparseable or not literal, CL002 duplicate handle in a root, CL003 unknown handle in a literal `include`/`embed`/`extends` anywhere under `@templates`, CL004 unknown handle in a story, CL005 legacy config failed to render or decode. Warnings: CL006 unresolved legacy placeholder, CL007 tag and config on one file, CL008 dynamic include name skipped. It exits 1 on any error (or any warning with `--strict`), else 0. |
| BR-32 | `craft component-library/make <category>/<name> [--root=<n>] [--folder]` writes `<name>.twig` (a tag with `name` and `status: 'wip'`) and `<name>.stories.twig` (one *Default* story) into the first root, or root *n*. `--folder` nests them in `<name>/`. It refuses to overwrite and validates segments against `[a-z0-9-]+`. |

**Non-functional**

| # | Rule |
|---|---|
| BR-33 | Once the index is warm, a page with 50 `@handle` includes does no directory iteration and no config rendering. A cold build of LLL's 103 files completes within one request. |
| BR-34 | The viewer is built from Craft CP form macros and components. Every control is labelled and keyboard-reachable, with visible focus and a logical order. The iframe `title` names the component and story. It's usable at 375 px wide. |
| BR-35 | Craft `^5.0`, PHP `^8.2`. Single-site installs show no site switch and otherwise work unchanged. |
| BR-36 | A share row holds a label, the creator id and timestamps, and nothing about the recipient. |

---

## 6. Interfaces

| Method | Path | Purpose | Auth | Returns |
|---|---|---|---|---|
| GET | `admin/component-library` | Viewer: first component or empty state | `accessPlugin-component-library` | CP page |
| GET | `admin/component-library/<handle>?story=&site=&props=` | Viewer on one component (`site` picks the iframe base URL only, per BR-22) | `accessPlugin-component-library` | CP page |
| GET | `admin/component-library/shares` | Share list and create form | + `manageComponentLibraryShares` | CP page |
| POST | `actions/component-library/shares/create` | Create a link | + manage, CSRF | Redirect, one-time URL in the flash |
| POST | `actions/component-library/shares/revoke` | Cancel a link | + manage, CSRF | Redirect |
| GET | `<primary site>/component-library/share/<token>[/<handle>]` | Share viewer | Anonymous, active token | Page / 404 / 410 |
| GET | `<site base URL>?token=<craft token>&component=&story=&props=` | Preview render | Anonymous, valid Craft token, scope rechecked | HTML / 403 / 500 |

v1's front-end `component-library` and `component-library/render` URLs and the
`get-component-info` action are **removed**.

**Screens**

| Surface | New or reuse | Notes |
|---|---|---|
| Viewer (CP) | New, on the CP layout | Tree and search sidebar. Preview with site switch (hidden on single-site) and viewport widths. Tabs: Settings, Examples, Source, Notes. Site-version badge. |
| Share links (CP) | New, CP table and form | One-time URL panel with copy. Cancel with confirmation. |
| Share viewer | New, same templates outside the CP | CP stylesheet, no CP nav. Header with label and expiry. |
| Expired, cancelled and unknown link pages | New | Plain: one sentence and a suggestion |
| Error panel | New | Inside the preview. Detail depends on scope (BR-26). |

**Design source.** No bespoke design. The developer builds in Craft's CP look with its form macros,
and the share viewer reuses the same templates and CP stylesheet.

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

### Test data and preconditions

| Fixture | Exists? |
|---|---|
| Sandbox `~/projects/craft5`, admin via `users/impersonate admin` | Yes |
| `restricted` user: has `accessCp`, lacks the new permission, so it is the refused user | Yes |
| Group `clViewers` with `accessCp` and `accessPlugin-component-library` only, and user `clviewer` | Yes, `tests/fixtures/setup.sh` |
| Second sandbox site `second`, base URL `$PRIMARY_SITE_URL/second/` | Yes, `tests/fixtures/setup.sh` |
| Sandbox roots at `tests/fixtures/templates` (and `_sites`), no v1 module lines in `config/app.php` | Yes, `tests/fixtures/setup.sh` |
| `tests/fixtures/templates/`: `good` (tag + stories), `nested` (a story embedding another component with a block override), `bad-tag`, `legacy/button` (Twig-wrapped config with `variants`, `variables`, `{include:}` and a readme), `throws`, `raw-prop` (`\|raw` on a string prop), `_sites/second/` overriding `good`, and a page with 50 `@handle` includes. Plus `tests/fixtures/edge/`, a root outside the sandbox config (TN-8's duplicates, and in `edge/legacy/` TN-9's failing config, `{ref:}`/`{entry:}`/`{asset:}` placeholders, and a tag beside a config) | Partly: `good`, `nested`, `bad-tag`, `legacy/button`, `_sites/second/` and `edge/` exist (2.1 to 2.4). The rest come with 2.5 and 3.1 |
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
2. Switch to v2 on a throwaway local branch (the `config/app.php` swap and plugin install only), fetch again and `diff`. Only CSRF tokens and asset hashes should differ.
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

**TS-14 · Keyboard and small screen** · AC-14 · BR-34, BR-35 · *manual browser*
*Success criterion: journey 1 is complete by keyboard and at 375 px.*
1. Tab from the top through the tree, search, controls, tabs and site switch. Every control is reachable with visible focus in a logical order, and Enter or Space operates each.
2. At 375 px, do journey 1. There's no horizontal page scroll, and the tree collapses behind a toggle.

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

### Automated checks

Pest 2 with `markhuot/craft-pest-core` 3 (`tests/`) covers every scenario marked *Pest* and every
TN row, plus unit tests for handle derivation (BR-12, BR-13), story isolation (BR-9) and the legacy
`variables` mapping (BR-15). The suite runs from the sandbox root: craft-pest-core boots Craft
with `CRAFT_BASE_PATH = getcwd()`, and Pest loads `tests/Pest.php` only from `--test-directory`,
so B5 #3 passes both `-c` and `--test-directory`. Each test runs in a rolled-back transaction.
Fixtures that must outlive a test come from `tests/fixtures/setup.sh`, and `tests/HarnessTest.php`
fails if they are missing. TS-3 step 3, TS-8 and TS-9 run against other projects' real pages.
TS-14 needs a human judgement on focus order. TS-2 and TS-3 steps 1–2 stay browser runs until an
e2e runner targets the plugin (the sandbox has Playwright, but nothing points at the plugin yet).

**Test hooks the build must add:**
- `data-cl-component="<handle>"` (tree items), `data-cl-story="<name>"`, `data-cl-prop="<name>"`
- `data-cl-preview` (iframe), `data-cl-error` (error panel), `data-cl-site-version` (badge)
- `data-cl-share-state="active|expired|cancelled"`, `data-cl-share-url` (one-time URL)
- A test-only index build counter, reset per test

### Regression checks

| Journey | Why it is at risk |
|---|---|
| mw-core `@handle` includes on all 8 sites | New loader, and re-implemented site precedence (TS-9) |
| mw-core dynamic `@blocks:#{block.type\|kebab}` includes | Invisible to the check, and must still resolve (BR-17) |
| mw-core `include("_sites/#{currentSite.handle}/head.twig", ignore_missing=true)` | A plain path the loader must pass through untouched |
| webdna's two `{include:@…}` legacy defaults | Placeholder support narrows (BR-16) |
| Ordinary CP pages and other plugins' CP templates | v1 swapped the whole Twig loader. A v2 mistake here breaks the CP. |
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
touching other repositories.

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
- [ ] **2.5 Loader**: `src/twig/Loader.php`, wrapping Craft's loader for owned names only, on both the site and CP Twig instances
      Rules: BR-17, BR-18 · Verify: TN-5, TN-6, Pest `LoaderTest`
- [ ] **2.6 Resolver service**: `src/services/Resolver.php`, with the signature exactly as BR-19
      Rules: BR-19 · Verify: TN-17
- [ ] **3.1 Render action**: `src/controllers/RenderController.php`, `src/services/Renderer.php`, `src/templates/_render/{layout,error}.twig` (scope recheck, guest identity, prop coercion, headers, error scopes)
      Rules: BR-20 to BR-26 · Verify: TS-7, TS-10, TN-1 to TN-4, TN-16
- [ ] **3.2 CP viewer**: `src/controllers/ViewerController.php`, `src/templates/viewer/*`, `src/web/assets/viewer/{ViewerAsset.php,viewer.js,viewer.css}` (tree, search, controls from props, stories, site switch, source, notes, URL state, empty states, test hooks)
      Rules: BR-2, BR-22, BR-34, BR-35 · Verify: TS-2, TS-3, TS-14, TN-15
- [ ] **4.1 Share service and management**: `src/services/Shares.php`, `src/controllers/SharesController.php`, `src/templates/shares/*`, GC hook, user-delete cascade
      Rules: BR-3, BR-27, BR-28, BR-30, BR-36 · Verify: TS-4, TN-11 to TN-13
- [ ] **4.2 Share viewer**: `src/controllers/ShareViewerController.php` (site URL rule), share layout reusing the viewer templates with the CP stylesheet, and the expired, cancelled and unknown pages. Settle Appendix A row 2 first.
      Rules: BR-26, BR-27, BR-29 · Verify: TS-5, TS-6
- [ ] **5.1 Check command**: `src/console/controllers/CheckController.php`
      Rules: BR-31 · Verify: TS-11, B5 #4
- [ ] **5.2 Make command**: `src/console/controllers/MakeController.php`
      Rules: BR-32 · Verify: TS-12
- [ ] **5.3 Docs**: `README.md`, `docs/{setup,format,share-links,upgrading-from-v1,resolver}.md`, a `CHANGELOG.md` 2.0.0 entry
      Rules: BR-4, BR-19 · Verify: Sam reads the upgrade guide against TS-9's steps
- [ ] **6.1 LLL install and pilot** (LLL repo, own branch): `config/component-library.php`, `templates/_component-library/preview.twig` (the craft-vite pair from `_layouts/public.twig:38-40`), convert `_components/ui/button.twig`, `form/text.twig` and `ui/dialog.twig`, and add their `.stories.twig`
      Rules: BR-7, BR-8, BR-25 · Verify: TS-8, B5 #5
- [ ] **6.2 Consumer back-compat proof** (mw-core and webdna on throwaway local branches, never committed): `$CLAUDE_JOB_DIR/tmp/compat.sh` fetch-and-diff script
      Rules: BR-15, BR-17, BR-25 · Verify: TS-9, B5 #6

---

# Appendix A: Open questions

| # | Question | Owner | State |
|---|---|---|---|
| 1 | Share-link wording: the viewer header, the expired, cancelled and unknown pages, and the one-time URL notice | Sam | Open, blocking release |
| 2 | Does Craft's CP stylesheet (`CpAsset`) load and style correctly on a site request, without the CP JS globals? If not, the share viewer ships a copied subset of CP CSS. | Developer | Open, blocking task 4.2 |
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

## B4. Definition of done

- [ ] Every AC in §7 passes. The manual evidence for TS-3 step 3, TS-8, TS-9 and TS-14 is recorded
      in the task commit or the build-progress memory.
- [ ] Every BR in §5 is enforced in code, with a Pest test or a named manual scenario
- [ ] TN-1 to TN-17 behave as specified, and the §7 regression checks pass
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

# 4. Library check on the clean fixture config
cd ~/projects/craft5 && ddev craft component-library/check
# expect: last line "0 problems", exit 0

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
