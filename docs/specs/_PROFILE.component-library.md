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
| Tests | none | Pest 2.36 + `markhuot/craft-pest-core` 3.2, PHPStan level 5, ECS (Craft ruleset) |
| Sandbox | `~/projects/craft5`: DDEV, Craft 5.11.1, PHP 8.3, MySQL 8.0, `https://craft5.ddev.site`, single site `default` | same |
| First adopter | `~/Projects/lll`: DDEV, Craft 5.11.3, PHP 8.4, Vite 6 + Tailwind 3 via craft-vite, Alpine 3 | same |

## 2. Commands

Nothing runs on the host except git and rsync. PHP, Composer and Craft run inside the sandbox's DDEV.

```bash
# Push the working copy into the sandbox. The sandbox holds a COPY (composer path repo
# "plugins/*"), not a symlink, so edits here are invisible there until this runs.
rsync -a --delete --exclude .git --exclude vendor ./ ~/projects/craft5/plugins/component-library/

cd ~/projects/craft5 && ddev composer <command>
cd ~/projects/craft5 && ddev craft <command>          # e.g. plugin/install component-library
cd ~/projects/craft5 && ddev craft clear-caches/all
cd ~/projects/craft5 && ddev craft users/impersonate admin   # prints a login URL (see Traps)
cd ~/projects/craft5 && ddev snapshot                  # before install/uninstall/migration work
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
cd ~/projects/craft5 && ddev exec vendor/bin/phpstan analyse -c plugins/component-library/phpstan.neon --no-progress --memory-limit=1G
# expect: [OK] No errors

# 2. Coding standard
cd ~/projects/craft5 && ddev exec vendor/bin/ecs check --config plugins/component-library/ecs.php
# expect: [OK] No errors found

# 3. Tests
cd ~/projects/craft5 && ddev exec vendor/bin/pest -c plugins/component-library/phpunit.xml --test-directory=plugins/component-library/tests
# expect: Tests: N passed — no failed, no risky (read the line: Pest 2 exits 0 on risky)

# 4. Component check
cd ~/projects/craft5 && ddev craft component-library/check
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
- **`ddev` has no `--dir` flag.** `cd ~/projects/craft5 && ddev …` works under the worktree guard.
- **`craftcms/phpstan` does not install PHPStan.** The sandbox has `phpstan/phpstan ^2.1` (1.x
  conflicts with its `composer/pcre`), `craftcms/phpstan` and `craftcms/ecs` as dev deps.
- **Craft gates `admin/<plugin-handle>/…` itself.** Any CP request whose first segment is an
  installed plugin's handle needs `accessPlugin-<handle>`, checked in `web/Application.php` before
  the controller, whether or not the plugin has a CP section. That's why BR-1 uses it.
- **The sandbox's second site `second` is a fixture.** `bash tests/fixtures/setup.sh` (after the
  rsync) creates it along with group `clViewers`, user `clviewer` and the fixture roots. It is
  idempotent. Rerun it after any snapshot restore older than task 1.2.
- **Pest loads `tests/Pest.php` only from `--test-directory`.** Run as `pest plugins/component-library/tests`,
  the tests still pass because Craft boots anyway, but they aren't bound to craft-pest's `TestCase`
  and get no transaction rollback. `tests/HarnessTest.php` fails loudly on this.
- **craft-pest-core applies pending project config before every run** and boots Craft from the cwd.
  So a DB change that never reached the YAML gets reverted by the next test run. A script that
  saves project config without running a request must call `ProjectConfig::flush()`, not
  `saveModifiedConfigData()`: only `flush()` writes the YAML.
- **The sandbox is pinned to PHPUnit 10** by `codeception/lib-innerbrowser`, so Pest is 2.x.
  `pestphp/pest-plugin` is allowed in the sandbox's `allow-plugins`. Install with `-w`, never `-W`:
  `-W` also upgrades Craft.
- **Pest 2 ignores `failOnRisky`** (`Result::exitCode` returns success first). Read the `Tests:` line.

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
