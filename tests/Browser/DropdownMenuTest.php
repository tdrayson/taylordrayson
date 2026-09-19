<?php

/*
 * The menu primitive's behaviour is the point of extracting it, so it is tested
 * through a real consumer rather than in isolation.
 */

it('opens the time jump menu and closes it on Escape', function () {
    $page = visit('/');

    $page->assertDontSee('On this day')
        ->click('[aria-label="Open time navigation"]')
        ->assertSee('On this day')
        ->keys('[role="menu"]', 'Escape')
        ->assertDontSee('On this day');
});

it('moves the roving-tabindex highlight on arrow keys and follows it on Enter', function () {
    $page = visit('/')->resize(1280, 800);

    $page->click('[aria-label="Open time navigation"]');

    // Opening the menu focuses the first row: the highlight and DOM focus
    // start in agreement, not just the activeIndex ref on its own.
    $page->assertScript("document.activeElement.getAttribute('role')", 'menuitem')
        ->assertScript("document.activeElement.getAttribute('data-active')", 'true')
        ->assertScript('document.activeElement.textContent.trim()', 'Today');

    // ArrowDown moves both the highlight and DOM focus onto the next row.
    $page->keys('[role="menu"]', 'ArrowDown')
        ->assertScript("document.activeElement.getAttribute('data-active')", 'true')
        ->assertScript('document.activeElement.textContent.trim()', 'This month');

    // Two more land on the static "On this day" row, so Enter's destination
    // doesn't depend on today's date.
    $page->keys('[role="menu"]', 'ArrowDown')
        ->keys('[role="menu"]', 'ArrowDown')
        ->keys('[role="menu"]', 'Enter')
        ->assertScript('window.location.pathname', '/on-this-day');
});
