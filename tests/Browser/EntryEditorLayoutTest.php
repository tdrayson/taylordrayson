<?php

use App\Models\User;

it('switches the writing column between tabs, keeping the title in view', function () {
    $this->actingAs(User::factory()->create());

    visit('/new/article')
        ->assertPresent('button[aria-pressed]:has-text("Content")')
        ->assertPresent('button[aria-pressed]:has-text("Summary")')
        ->assertPresent('button[aria-pressed]:has-text("Response")')
        ->assertPresent('button[aria-pressed]:has-text("Social")')
        ->assertMissing('#excerpt')
        ->click('button[aria-pressed]:has-text("Summary")')
        ->assertVisible('#excerpt')
        ->assertVisible('#title')
        ->assertNoJavascriptErrors();
});
