<?php

use App\Models\Note;

// The dynamic icon maps (navigation.js / entryTypes.js) store registry-name
// STRINGS rather than imported hugeicons objects; Icon.vue resolves a string via
// the registry. A broken name would resolve to null and render nothing.
it('renders map-driven registry-name icons as svg', function () {
    $page = visit('/')->resize(1280, 900);

    $page->click('[aria-label="Open search"]')
        ->type('[role="dialog"] input', 'Advanced')
        ->assertScript(
            // Search01Icon from pageCommands — assert a real SVG, not an empty button.
            "[...document.querySelectorAll('[role=\"dialog\"] button')].some((btn) => btn.textContent.includes('Advanced search') && btn.querySelector('svg'))",
            true,
        );
});

it('renders timeline entry-type icons from the registry', function () {
    Note::factory()->create([
        'content' => 'Registry icon check',
        'occurred_at' => now()->subHour(),
    ]);

    $page = visit('/');

    // Note cards use entryTypes.note.icon = 'Note01Icon'.
    $page->assertScript(
        "document.querySelector('.h-entry svg') !== null",
        true,
    );
});
