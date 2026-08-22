<?php

it('keeps the avatar and hamburger still when the menu opens', function () {
    $page = visit('/')->resize(390, 844);

    // Going full-screen takes the shell out of flow, so with the band left
    // behind in the layout the row jumps up by the band's height.
    $page->assertScript("
        (() => {
            const row = () => document.querySelector('[data-status-band]')
                .parentElement
                .querySelector('a[href=\"/\"]')
                .getBoundingClientRect().top;

            window.__before = Math.round(row());
            return true;
        })()
    ", true);

    $page->click('[aria-label="Open menu"]');

    $page->assertScript("
        (() => {
            const top = Math.round(document.querySelector('[data-status-band]')
                .parentElement
                .querySelector('a[href=\"/\"]')
                .getBoundingClientRect().top);
            return Math.abs(top - window.__before) <= 1;
        })()
    ", true);
});

it('leaves the status band visible above the open menu', function () {
    $page = visit('/')->resize(390, 844);

    $page->click('[aria-label="Open menu"]');

    $page->assertScript("document.querySelector('[data-status-band]').getBoundingClientRect().top >= 0", true);
});
