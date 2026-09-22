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

it('opens the numeronym info on hover and on Enter', function () {
    $page = visit('/')->resize(1280, 800)->click('[aria-label="Open settings"]');
    $expanded = "document.querySelector('[aria-label=\"About numeronym mode\"]').getAttribute('aria-expanded')";

    $page->hover('[aria-label="About numeronym mode"]')
        ->assertScript($expanded, 'true')
        ->hover('[aria-label="Close settings"]')
        ->wait(0.3)
        ->assertScript($expanded, 'false')
        ->keys('[aria-label="About numeronym mode"]', 'Enter')
        ->assertScript($expanded, 'true');
});

it('resets every setting to its default', function () {
    visit('/')->script(clearCookies());
    $page = visit('/')->resize(1280, 800)->click('[aria-label="Open settings"]');
    $resetShown = "[...document.querySelectorAll('[role=\"dialog\"] button')].some((b) => b.textContent.includes('Reset to defaults'))";

    $page->assertScript($resetShown, false)
        ->click('[aria-label="Distance unit"] [aria-label="km"]')
        ->click('[aria-label="Dark"]')
        ->assertScript($resetShown, true)
        ->click('Reset to defaults')
        ->assertScript(cookieValue('pref_distanceUnit'), 'mi')
        ->assertScript(cookieValue('theme'), 'system')
        ->assertScript($resetShown, false);
});
