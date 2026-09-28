# Project profile — webdna/component-library

**Referenced from the front matter of every spec in `docs/specs/`.** It carries the project-wide
half of a spec's build contract, so individual specs can stay about their feature.

> **Agents: read this whole file before writing code.** The Traps section is evidence from this
> repository's history, its v1 code and its sandbox. It is not general advice.

This repository is a **Craft CMS package with no Craft install of its own.** Code is written here and
exercised in the sandbox project `~/projects/craft5`. Consumers (mw-core, webdna, LLL) are separate
repos and are never edited from here.

---

## 1. Stack

| Layer | v1 (today, `main`) | v2 (target) |
|---|---|---|
| Package | `webdna/component-library`, `type: craft-module` | same name, `type: craft-plugin`, handle `component-library` |
| Craft | `^3.7\|^4.0.0\|^5.0.0` | `^5.0` only |
| PHP | unconstrained | `^8.2` |
| Namespace | `webdna\componentlibrary\` → `src/` (PSR-4) | unchanged |
| Viewer UI | Tailwind + Alpine from public CDNs, hand-written `app.js` | Craft CP templates and CP styles, vanilla JS, no build step |
| Tests | none | Pest + `markhuot/craft-pest-core`, PHPStan level 5, ECS (Craft ruleset) |
| Sandbox | `~/projects/craft5`: DDEV, Craft 5.11.1, PHP 8.3, MySQL 8.0, `https://craft5.ddev.site`, single site `default` | same |
| First adopter | `~/Projects/lll`: DDEV, Craft 5.11.3, PHP 8.4, Vite 6 + Tailwind 3 via craft-vite, Alpine 3 | same |

## 2. Commands

Nothing runs on the host except git and rsync. PHP, Composer and Craft run inside the sandbox's DDEV.

```bash
# Push the working copy into the sandbox. The sandbox holds a COPY (composer path repo
# "plugins/*"), not a symlink, so edits here are invisible there until this runs.
rsync -a --delete --exclude .git --exclude vendor ./ ~/projects/craft5/plugins/component-library/

ddev --dir ~/projects/craft5 composer <command>
ddev --dir ~/projects/craft5 craft <command>          # e.g. plugin/install component-library
ddev --dir ~/projects/craft5 craft clear-caches/all
ddev --dir ~/projects/craft5 craft users/impersonate admin   # prints a login URL (see Traps)
ddev --dir ~/projects/craft5 snapshot                  # before install/uninstall/migration work
```

## 3. Where things go

| What | Where |
|---|---|
| Plugin class | `src/ComponentLibrary.php` |
| Services | `src/services/` |
| Models and records | `src/models/`, `src/records/` |
| Install migration | `src/migrations/Install.php` |
| Controllers (CP and site) | `src/controllers/` |
| Console commands | `src/console/controllers/` |
| Twig loader, tags, nodes, extension | `src/twig/` |
| CP and share-viewer templates | `src/templates/` |
| Viewer JS/CSS | `src/web/assets/viewer/` (AssetBundle + plain files, no build) |
| Tests | `tests/` (Pest), run from the sandbox |
| Docs | `docs/` (user docs), `docs/specs/` (specs) |

**Placement rule:** runtime code in `src/`, never in the sandbox. A sandbox file is a fixture and gets
overwritten by the next rsync.

## 4. Verification

```bash
# 1. Static analysis
ddev --dir ~/projects/craft5 exec vendor/bin/phpstan analyse -c plugins/component-library/phpstan.neon --no-progress
# expect: [OK] No errors

# 2. Coding standard
ddev --dir ~/projects/craft5 exec vendor/bin/ecs check plugins/component-library/src
# expect: [OK] No errors found

# 3. Tests
ddev --dir ~/projects/craft5 exec vendor/bin/pest plugins/component-library/tests
# expect: Tests: N passed — no failed, no risky

# 4. Component check
ddev --dir ~/projects/craft5 craft component-library/check
# expect: exit 0, final line "0 problems"
```

Always rsync (section 2) before 1-4. A green run against a stale copy proves nothing.

## 5. Traps

### Sandbox

- **The `restricted` fixture user (id 71, group `ptRestricted`) has `accessCp`.** It cannot be the
  "refused" user for a CP-only gate. To prove a refusal, create a throwaway user
  (`craft users/create … --admin=0`) and delete it afterwards.
- **Admin sessions come from `craft users/impersonate admin`.** It prints a URL. Curl it with a cookie
  jar and `-L`. There is no admin password to type.
- **The sandbox `.env` has no trailing newline.** Append with a leading `\n`. If you don't, dotenv
  fails to parse and every request fatals.
- **The worktree guard rejects shell `for` loops and `bash -c` wrappers around ddev/mysql.** Put
  multi-step checks in a script under `$CLAUDE_JOB_DIR/tmp` and run it with `bash`.
- **The sandbox has one site (`default`).** Multi-site behaviour cannot be proved there without adding
  a second site as a fixture.

### Twig and Craft (from v1)

- **v1 replaces Craft's Twig loader for the whole app** (`ComponentLibrary.php:43`,
  `setLoader(new ComponentLibraryLoader)`), inside `onInit`. That affects only the Twig instance for
  the template mode current at that moment. Anything that swaps or wraps the loader must handle
  both site and CP template modes, and must delegate every name it does not own, or ordinary
  templates stop resolving.
- **`renderString()` on anything that came from data is Twig injection.** v1 built Twig source from
  config values (`{include:}` placeholder, fixed in 5824487) and Twig-renders every `.config.json` and
  `readme.md`. Data is passed as context to `renderTemplate()`, never concatenated into a template.
- **Request parameters are hostile.** v1 let any query parameter override context. That parameter
  then flowed into `{ref:}`/`{entry:}` resolution (element disclosure) and, through `?site=`, into a
  filesystem path (traversal). A site handle is valid only once `getSiteByHandle()` has returned it.
  A request value is never a path segment.
- **A secret compared with `!=` against an unset env var passes.** `getenv()` returns `false`, the
  key param is `''`, and the gate fails open (v1 view key, fixed in 5824487). Require a non-empty
  secret, compare with `hash_equals`, and read with `App::env()`.
- **Single-site installs break site pickers.** `db4ca44` ("Fix for non multisite") had to add a
  hidden site input because the UI assumed a picker existed. Every site-aware screen must work with
  one site.
- **The v1 component-map cache is dead code** (`Formatters.php:101-103`, the `return` is commented
  out). Every `@handle` include rescans every directory and Twig-renders every config. Do not copy
  that shape.

## 6. Working conventions

- Branch per piece of work. **Commit, never push.** Sam pushes.
- `ddev snapshot` the sandbox before plugin install/uninstall or migration testing.
- Leave consumer repos (mw-core, webdna, LLL) untouched unless a spec task names the file.
- One spec task per session. Tick the spec and update the build-progress memory before ending.
