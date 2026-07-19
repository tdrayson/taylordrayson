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

    // Open it from the sidebar search trigger.
    $page->click('Search')
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
