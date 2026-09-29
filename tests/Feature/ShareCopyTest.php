<?php

declare(strict_types=1);

/*
 * Copying a share link again (task 4.3): TS-18 steps 1-3 and TN-22. The copy prompts themselves
 * are Craft's JS, so TS-18 step 4 is a browser run.
 */

use craft\elements\User;
use craft\helpers\Db;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\controllers\SharesController;
use webdna\componentlibrary\models\ShareForm;
use webdna\componentlibrary\records\ShareRecord;

const COPY_PAGE = 'admin/component-library/shares';
const COPY_ACTION = 'admin/actions/component-library/shares/url';

beforeEach(function() {
    $this->withExceptionHandling();
    $this->shares = ComponentLibrary::getInstance()->getShares();
    ShareRecord::deleteAll();
    Craft::$app->getSession()->removeFlash(SharesController::FLASH_URL);

    // An active link made through the service, as the create form does, and its token.
    $this->make = function(string $label = 'Acme'): array {
        $form = ShareForm::blank();
        $form->label = $label;
        $token = (string)$this->shares->create($form, (int)User::find()->username('admin')->one()->id);

        return [ShareRecord::findOne(['tokenHash' => hash('sha256', $token)]), $token];
    };
});

describe('TS-18 copy link', function() {
    it('offers Copy link beside Revoke link on an active row', function() {
        [$share] = ($this->make)();
        $html = $this->actingAs('admin')->get(COPY_PAGE)->assertOk()->content;

        expect($html)->toContain('data-cl-copy-share="' . $share->id . '"')
            ->and(strpos($html, 'data-cl-copy-share'))->toBeLessThan((int)strpos($html, 'data-action="component-library/shares/revoke"'));
    });

    it('returns the address the create showed', function() {
        [$share, $token] = ($this->make)();
        $response = $this->actingAs('admin')->post(COPY_ACTION, ['id' => $share->id])->assertOk();

        expect(json_decode($response->content, true))->toBe(['url' => $this->shares->url($token)]);
    });

    it('never puts a token in the list', function() {
        [, $token] = ($this->make)();
        $html = $this->actingAs('admin')->get(COPY_PAGE)->assertOk()->content;

        expect($html)->not->toContain($token);
    });

    it('offers no copy, and refuses it, for a link that is expired or has no stored token', function(string $case) {
        [$share] = ($this->make)();
        match ($case) {
            'expired' => $share->expiresAt = Db::prepareDateForDb(new DateTime('-1 hour')),
            default => $share->tokenEncrypted = null,
        };
        $share->save(false);

        // The attribute, not the script's selector for it.
        expect($this->actingAs('admin')->get(COPY_PAGE)->assertOk()->content)->not->toContain('data-cl-copy-share="');
        $this->actingAs('admin')->post(COPY_ACTION, ['id' => $share->id])->assertStatus(404);
    })->with(['expired', 'no token']);

    it('refuses a revoked link, which no longer exists', function() {
        [$share] = ($this->make)();
        $this->shares->revoke((int)$share->id);

        $this->actingAs('admin')->post(COPY_ACTION, ['id' => $share->id])->assertStatus(404);
    });
});

describe('TN-22 the url action', function() {
    it('is 404 for an unknown or non-numeric id', function(mixed $id) {
        ($this->make)();
        $this->actingAs('admin')->post(COPY_ACTION, ['id' => $id])->assertStatus(404);
    })->with(['unknown' => [999999], 'text' => ['abc'], 'zero' => [0]]);

    it('is 403 without the manage permission', function() {
        [$share] = ($this->make)();
        $this->actingAs('clviewer')->post(COPY_ACTION, ['id' => $share->id])->assertStatus(403);
    });

    it('takes POST only, with a CSRF token', function() {
        [$share] = ($this->make)();
        $this->actingAs('admin')->get(COPY_ACTION . '?id=' . $share->id)->assertStatus(405);
        $this->actingAs('admin')->http('post', COPY_ACTION)->setBody(['id' => $share->id])->send()->assertStatus(400);
    });

    it('refuses a stored token that does not decrypt to this link', function() {
        [$share] = ($this->make)();
        [, $other] = ($this->make)('Other');
        $share->tokenEncrypted = base64_encode(Craft::$app->getSecurity()->encryptByKey($other));
        $share->save(false);

        $this->actingAs('admin')->post(COPY_ACTION, ['id' => $share->id])->assertStatus(404);
    });
});

describe('TS-4 the create prompt', function() {
    it('hands the new address to the copy prompt once', function() {
        $this->actingAs('admin')->post(COPY_PAGE, ['action' => 'component-library/shares/create', 'label' => 'Acme', 'expiry' => ShareForm::day(14)]);

        $first = $this->get(COPY_PAGE)->assertOk()->content;
        expect($first)->toMatch('#data-cl-share-url="[^"]+/component-library/share/[A-Za-z0-9_-]{43}"#')
            ->and($first)->toContain('Craft.ui.createCopyTextPrompt')
            ->and($this->get(COPY_PAGE)->assertOk()->content)->not->toContain('data-cl-share-url=');
    });
});
