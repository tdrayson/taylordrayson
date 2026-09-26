<?php

use App\Models\User;

it('reveals the response url only once a kind is chosen', function () {
    $this->actingAs(User::factory()->create());

    $page = visit('/new/note');

    // Hidden until a kind is picked: showWhen response_kind => [] means any value.
    $page->assertMissing('#response_url')
        ->assertMissing('#rsvp_value');

    $page->click('button[aria-pressed]:has-text("Response")');
    $page->select('#response_kind', 'reply');
    $page->assertPresent('#response_url')
        // An answer belongs to an RSVP alone.
        ->assertMissing('#rsvp_value')
        ->assertNoJavascriptErrors();
});

it('reveals the answer only for an rsvp, and clears it when the kind changes', function () {
    $this->actingAs(User::factory()->create());

    $page = visit('/new/note');

    $page->click('button[aria-pressed]:has-text("Response")');
    $page->select('#response_kind', 'rsvp');
    $page->assertPresent('#rsvp_value')->assertPresent('#response_url');

    // Leaving RSVP hides the answer, and a hidden field is cleared.
    $page->select('#response_kind', 'like');
    $page->assertMissing('#rsvp_value')
        ->assertNoJavascriptErrors();
});
