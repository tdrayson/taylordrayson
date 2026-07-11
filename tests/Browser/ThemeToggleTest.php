<?php

it('applies the dark class when Dark is selected and reverts on Light', function () {
    // Desktop width so the sidebar gear (md:flex) is visible; the mobile
    // nav's copy is v-if'd out of the DOM until the menu is opened, so
    // [aria-label="Open settings"] resolves uniquely here.
    $page = visit('/')->resize(1280, 800);

    // ThemeToggle now lives inside the settings modal, opened via the gear.
    $page->click('[aria-label="Open settings"]');

    // Select Dark and assert the root carries the dark scope.
    $page->click('[aria-label="Dark"]')
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->assertScript("localStorage.getItem('theme')", 'dark');

    // Select Light and assert it reverts.
    $page->click('[aria-label="Light"]')
        ->assertScript("document.documentElement.classList.contains('dark')", false)
        ->assertScript("localStorage.getItem('theme')", 'light');
});
