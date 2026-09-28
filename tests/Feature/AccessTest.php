<?php

declare(strict_types=1);

/*
 * TS-1 and TN-11: who can reach the viewer and the share-link routes (BR-1, BR-2, BR-3).
 *
 * `restricted` (sandbox fixture) has accessCp but not the plugin permission, `clviewer`
 * (tests/fixtures/setup.sh) has the view permission only, and `admin` holds everything.
 * Every "does not see" assertion is paired with a user who does, so a wrong needle can't pass.
 */

use webdna\componentlibrary\records\ShareRecord;

const NAV_LINK = '/admin/component-library"';
const SHARES_LINK = '/admin/component-library/shares"';
const CREATE_ACTION = 'admin/actions/component-library/shares/create';
const REVOKE_ACTION = 'admin/actions/component-library/shares/revoke';

beforeEach(function() {
    $this->withExceptionHandling();
});

function shareRows(): int
{
    return (int)ShareRecord::find()->count();
}

// TS-1 step 1
it('refuses the viewer to a CP user without the plugin permission', function() {
    $this->actingAs('restricted')->get('admin/component-library')->assertStatus(403);
});

it('hides the nav item from a CP user without the plugin permission', function() {
    $this->actingAs('restricted')->get('admin/dashboard')->assertOk()->assertDontSee(NAV_LINK);
    $this->actingAs('clviewer')->get('admin/dashboard')->assertOk()->assertSee(NAV_LINK);
});

// TS-1 step 2
it('refuses the share list and share creation to a CP user without the plugin permission', function() {
    $this->actingAs('restricted');
    $rows = shareRows();

    $this->get('admin/component-library/shares')->assertStatus(403);
    $this->post(CREATE_ACTION, ['label' => 'Nope'])->assertStatus(403);
    expect(shareRows())->toBe($rows);
});

// TS-1 step 3
it('lets a viewer browse the library without the share links tab', function() {
    $this->actingAs('clviewer')->get('admin/component-library')->assertOk()->assertDontSee(SHARES_LINK);
    $this->actingAs('admin')->get('admin/component-library')->assertOk()->assertSee(SHARES_LINK);
});

it('refuses the share list to a viewer', function() {
    $this->actingAs('clviewer')->get('admin/component-library/shares')->assertStatus(403);
});

// TS-1 step 4
it('shows the share list to an admin', function() {
    $this->actingAs('admin')->get('admin/component-library/shares')->assertOk();
});

// TN-11
it('rejects share creation without a CSRF token', function() {
    $rows = shareRows();

    $this->actingAs('admin')
        ->http('post', CREATE_ACTION)
        ->setBody(['label' => 'No token'])
        ->send()
        ->assertStatus(400);
    expect(shareRows())->toBe($rows);

    // The token is what made the difference.
    expect($this->post(CREATE_ACTION, ['label' => 'No token'])->getStatusCode())->not->toBe(400);
});

it('refuses share creation to a viewer', function() {
    $rows = shareRows();

    $this->actingAs('clviewer')->post(CREATE_ACTION, ['label' => 'Nope'])->assertStatus(403);
    expect(shareRows())->toBe($rows);
});

// BR-3: revoke is gated and POST-only too
it('refuses share revocation to a viewer', function() {
    $this->actingAs('clviewer')->post(REVOKE_ACTION, ['id' => 1])->assertStatus(403);
});

it('accepts share actions only by POST', function(string $action) {
    $this->actingAs('admin')->get($action)->assertStatus(405);
})->with([CREATE_ACTION, REVOKE_ACTION]);
