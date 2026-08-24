<?php

it('opens the settings modal from the gear and toggles theme', function () {
    // Desktop width so the sidebar gear (md:flex) is visible; the mobile
    // nav's copy is v-if'd out of the DOM until the menu is opened, so
    // [aria-label="Open settings"]/[aria-label="Dark"] resolve uniquely here.
    $page = visit('/')->resize(1280, 800);

    // Modal closed initially: no rendered dialog element.
    $page->assertScript("document.querySelector('[role=\"dialog\"]') === null", true);

    // Open it from the gear.
    $page->click('[aria-label="Open settings"]')
        ->assertScript("!!document.querySelector('[role=\"dialog\"]')", true);

    // Select Dark and assert the theme applied.
    $page->click('[aria-label="Dark"]')
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->assertScript(cookieValue('theme'), 'dark');

    // The Dark card reflects the selection via aria-checked.
    $page->assertScript(
        "document.querySelector('[aria-label=\"Dark\"]').getAttribute('aria-checked')",
        'true',
    );

    // Select Light and assert it reverts.
    $page->click('[aria-label="Light"]')
        ->assertScript("document.documentElement.classList.contains('dark')", false)
        ->assertScript(cookieValue('theme'), 'light');
});
