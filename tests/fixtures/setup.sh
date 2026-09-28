#!/usr/bin/env bash
#
# Sandbox fixtures for the component-library tests (spec §7, task 1.2). Idempotent, so rerun it
# after a snapshot restore or whenever the fixtures are in doubt.
#
# Run from the plugin working copy, after the profile §2 rsync:
#     bash tests/fixtures/setup.sh
#
# Snapshot the sandbox first (`ddev snapshot`). It edits the sandbox's config/app.php and
# config/component-library.php, and adds a group, a user and a site.

set -euo pipefail

SANDBOX="${SANDBOX:-$HOME/projects/craft5}"
PLUGIN="$SANDBOX/plugins/component-library"

if [ ! -f "$PLUGIN/tests/fixtures/setup.php" ]; then
    echo "No synced plugin copy at $PLUGIN. Run the profile §2 rsync first." >&2
    exit 1
fi

# 1. v1 was registered as a module in config/app.php. v2 is a plugin and refuses to load as a
#    module, so those lines must go.
APP="$SANDBOX/config/app.php"
sed -i.cl-bak \
    -e '/use webdna\\componentlibrary\\ComponentLibrary;/d' \
    -e "/'component-library' => ComponentLibrary::class,/d" \
    -e "/'bootstrap' => \['component-library'\],/d" \
    "$APP"
rm -f "$APP.cl-bak"
if grep -q -i 'componentlibrary' "$APP"; then
    echo "config/app.php still mentions componentlibrary. Remove it by hand." >&2
    exit 1
fi
echo "app.php    no module registration"

# 2. Point the roots (BR-11) at the fixture templates. The first run keeps v1's config beside it.
CONFIG="$SANDBOX/config/component-library.php"
if [ -f "$CONFIG" ] && ! grep -q 'tests/fixtures/templates' "$CONFIG"; then
    mv "$CONFIG" "$CONFIG.v1.bak"
fi
cat > "$CONFIG" <<'PHP'
<?php

// Written by plugins/component-library/tests/fixtures/setup.sh. Edits here are overwritten.
return [
    'templateDirectories' => [
        '@root/plugins/component-library/tests/fixtures/templates',
    ],
    'sites' => '@root/plugins/component-library/tests/fixtures/templates/_sites',
    // The fixtures include a v1 component (legacy/button), so they read v1 configs.
    'legacy' => true,
];
PHP
echo "config     roots at tests/fixtures/templates"

# 3. Plugin install, group clViewers, user clviewer and site second, through Craft's own APIs.
cd "$SANDBOX"
ddev exec php plugins/component-library/tests/fixtures/setup.php
