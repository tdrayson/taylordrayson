<?php

// Exercises CommandPalette.vue, which adopts useDialog for body scroll-lock and
// focus save/restore only (closeOnEsc: false, trapFocus: false) while keeping
// its own bespoke ⌘K / Escape / arrow-key keyboard model. This covers the parts
// shared with the other overlays plus the parts that must stay untouched.
it('opens the command palette from the search trigger, searches, and closes on escape', function () {
    // Desktop width so the sidebar's "Search" trigger (md:flex) is visible.
    $page = visit('/')->resize(1280, 800);

    // Palette closed initially: no rendered dialog element.
    $page->assertScript("document.querySelector('[role=\"dialog\"]') === null", true);

    // Open it from the sidebar search trigger. Its accessible name is the
    // aria-label, not the visible "Search" text.
    $page->click('[aria-label="Open search"]')
        ->assertScript("!!document.querySelector('[role=\"dialog\"]')", true);

    // useDialog focuses the first focusable element in the panel, which is the
    // search input (the palette no longer self-focuses it).
    $page->assertScript(
        "document.activeElement === document.querySelector('[role=\"dialog\"] input')",
        true,
    );

    // Typing filters the static page commands locally (no server round trip
    // needed), so "Calendar" should render as a result immediately. Scoped to
    // the dialog so this can't false-positive on unrelated page text.
    $page->type('[role="dialog"] input', 'Calendar')
        ->assertScript(
            "document.querySelector('[role=\"dialog\"]').textContent.includes('Calendar')",
            true,
        );

    // Escape closes it (the palette's own onPanelKeydown handles this, not
    // useDialog's closeOnEsc, which is off here).
    $page->keys('[role="dialog"]', 'Escape')
        ->assertScript("document.querySelector('[role=\"dialog\"]') === null", true);
});

it('offers authoring commands only once signed in', function () {
    // Signed out: the /new routes are auth-gated, so the palette must not
    // advertise them. Scoped to the rendered dialog, not the props JSON.
    $page = visit('/')->resize(1280, 800);

    $page->click('[aria-label="Open search"]')
        ->assertScript("document.querySelector('[role=\"dialog\"]').textContent.includes('Create')", false);

    $this->actingAs(App\Models\User::factory()->create());

    $page = visit('/')->resize(1280, 800);

    $page->click('[aria-label="Open search"]')
        ->assertScript("document.querySelector('[role=\"dialog\"]').textContent.includes('New note')", true)
        ->assertScript("document.querySelector('[role=\"dialog\"]').textContent.includes('Drafts')", true);

    // A type held back from the quick picks is still reachable by name.
    $page->type('[role="dialog"] input', 'flight')
        ->assertScript("document.querySelector('[role=\"dialog\"]').textContent.includes('New flight')", true)
        ->assertNoJavascriptErrors();
});

it('signs out from the palette', function () {
    // The iOS home-screen app has no address bar, so the palette is the only
    // way back out of a session. Only a whole-query match offers it.
    $this->actingAs(App\Models\User::factory()->create());

    visit('/')->resize(1280, 900)
        ->click('[aria-label="Open search"]')
        ->type('[role="dialog"] input', 'sign out')
        ->keys('[role="dialog"] input', 'Enter')
        // The signed-in quick-add button is gone, so the session really ended.
        ->assertScript("document.querySelector('[aria-label=\"Add an entry\"]') === null", true)
        ->assertNoJavascriptErrors();
});
