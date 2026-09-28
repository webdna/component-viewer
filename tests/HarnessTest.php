<?php

declare(strict_types=1);

use craft\elements\User;
use markhuot\craftpest\test\TestCase;
use webdna\componentlibrary\ComponentLibrary;

it('binds tests to the craft-pest test case', function() {
    // Fails if Pest never loaded tests/Pest.php, which it only does when run as profile §4 says.
    expect($this)->toBeInstanceOf(TestCase::class);
});

it('boots Craft with the plugin installed', function() {
    expect(Craft::$app->getPlugins()->getPlugin('component-library'))
        ->toBeInstanceOf(ComponentLibrary::class);
});

it('has the fixtures from tests/fixtures/setup.sh', function() {
    $group = Craft::$app->getUserGroups()->getGroupByHandle('clViewers');
    expect($group)->not->toBeNull('Run tests/fixtures/setup.sh');

    $permissions = Craft::$app->getUserPermissions()->getPermissionsByGroupId($group->id);
    sort($permissions);
    expect($permissions)->toBe(['accesscp', strtolower(ComponentLibrary::PERMISSION_VIEW)]);

    $user = User::find()->username('clviewer')->one();
    expect($user)->not->toBeNull()
        ->and($user->admin)->toBeFalse()
        ->and(array_map(fn($g) => $g->handle, $user->getGroups()))->toBe(['clViewers']);

    expect(Craft::$app->getSites()->getSiteByHandle('second'))->not->toBeNull();
});
