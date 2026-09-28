<?php

/**
 * Sandbox fixtures that outlive a test (spec §7, "Test data and preconditions"). Idempotent.
 *
 * Run by setup.sh, inside DDEV, from the sandbox root:
 *     ddev exec php plugins/component-library/tests/fixtures/setup.php
 */

declare(strict_types=1);

use craft\elements\User;
use craft\models\Site;
use craft\models\UserGroup;
use webdna\componentlibrary\ComponentLibrary;

require getcwd() . '/bootstrap.php';
require getcwd() . '/vendor/craftcms/cms/bootstrap/console.php';

$fail = static function(string $message): never {
    fwrite(STDERR, "setup.php: {$message}\n");
    exit(1);
};

// The plugin itself.
$plugins = Craft::$app->getPlugins();
if (!$plugins->isPluginInstalled('component-library')) {
    $plugins->installPlugin('component-library');
}
echo "plugin     component-library installed\n";

// Group clViewers: CP access and the view permission, nothing else. accessCp is required because
// Craft drops a nested permission whose parent is missing, and admin/* needs it anyway.
$userGroups = Craft::$app->getUserGroups();
$group = $userGroups->getGroupByHandle('clViewers');
if ($group === null) {
    $group = new UserGroup(['name' => 'Component library viewers', 'handle' => 'clViewers']);
    if (!$userGroups->saveGroup($group)) {
        $fail('could not save group clViewers: ' . implode(', ', $group->getFirstErrors()));
    }
}
Craft::$app->getUserPermissions()->saveGroupPermissions($group->id, ['accessCp', ComponentLibrary::PERMISSION_VIEW]);
$permissions = Craft::$app->getUserPermissions()->getPermissionsByGroupId($group->id);
sort($permissions);
echo 'group      clViewers: ' . implode(', ', $permissions) . "\n";

// User clviewer, in clViewers only, never an admin.
$user = User::find()->username('clviewer')->status(null)->one();
if ($user === null) {
    $user = new User([
        'username' => 'clviewer',
        'email' => 'clviewer@example.test',
        'fullName' => 'Component Library Viewer',
    ]);
}
$user->admin = false;
$user->active = true;
$user->pending = false;
if (!Craft::$app->getElements()->saveElement($user)) {
    $fail('could not save user clviewer: ' . implode(', ', $user->getFirstErrors()));
}
Craft::$app->getUsers()->assignUserToGroups($user->id, [$group->id]);
echo "user       clviewer (id {$user->id}) in clViewers\n";

// Site `second`, with its own base URL, in the primary site's group.
$sites = Craft::$app->getSites();
$site = $sites->getSiteByHandle('second', true);
if ($site === null) {
    $primary = $sites->getPrimarySite();
    $site = new Site([
        'groupId' => $primary->groupId,
        'name' => 'Second',
        'handle' => 'second',
        'language' => $primary->language,
        'hasUrls' => true,
        'baseUrl' => '$PRIMARY_SITE_URL/second/',
        'primary' => false,
    ]);
    if (!$sites->saveSite($site)) {
        $fail('could not save site second: ' . implode(', ', $site->getFirstErrors()));
    }
}
echo "site       second: {$site->getBaseUrl()}\n";

// Craft writes project config (the table and the YAML) at the end of a request, and this script
// never runs one. Without this the site row exists but project config doesn't know it, and the
// next project-config apply (craft-pest-core runs one before every suite) deletes it.
Craft::$app->getProjectConfig()->flush();
