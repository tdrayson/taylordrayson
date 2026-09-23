<?php

/*
 * FieldPicker and MultiSelect adopted useDismissable/useListboxNavigation in
 * place of hand-rolled dismissal and no keyboard support at all. Both open with
 * DOM focus already inside the listbox (a row for FieldPicker, the search input
 * for MultiSelect), which is what lets @keydown reach the handler at all.
 */

it('moves the field picker highlight on arrow keys and selects on enter', function () {
    $filter = json_encode([[
        'type' => 'note',
        'conditions' => [['field' => 'day', 'operator' => 'on', 'value' => null]],
    ]]);

    $page = visit('/search?filter='.urlencode($filter))->resize(1280, 800);

    $page->click('[aria-haspopup=true]');

    // Opening focuses the first row directly: the highlight and DOM focus
    // start in agreement, not just an activeIndex ref no listener can reach.
    $page->assertScript("document.activeElement.getAttribute('role')", 'option')
        ->assertScript("document.activeElement.getAttribute('data-active')", 'true')
        ->assertScript('document.activeElement.textContent.trim()', 'Day');

    $page->keys('[role=listbox]', 'ArrowDown')
        ->assertScript('document.activeElement.textContent.trim()', 'Month');

    $page->keys('[role=listbox]', 'ArrowDown')
        ->assertScript('document.activeElement.textContent.trim()', 'Year');

    $page->keys('[role=listbox]', 'Enter')
        ->assertScript("document.querySelector('[aria-haspopup=true]').textContent.trim()", 'When / Year');
});

it('moves the multi-select highlight on arrow keys and toggles selection on enter', function () {
    $filter = json_encode([[
        'type' => 'note',
        'conditions' => [['field' => 'status', 'operator' => 'is', 'value' => []]],
    ]]);

    $page = visit('/search?filter='.urlencode($filter))->resize(1280, 800);

    $trigger = 'button[aria-haspopup=true]:has-text("Select")';

    $page->click($trigger);

    // Opening focuses the search input directly, so the arrow keys below have
    // somewhere to bubble from.
    $page->assertScript("document.activeElement.getAttribute('aria-label')", 'Search options')
        ->assertScript("document.querySelector('[role=option][data-active=true]')?.textContent.trim()", 'Draft');

    $page->keys('[aria-label="Search options"]', 'ArrowDown')
        ->assertScript("document.querySelector('[role=option][data-active=true]')?.textContent.trim()", 'Published');

    $page->keys('[aria-label="Search options"]', 'Enter')
        ->assertScript("document.querySelector('[role=option][aria-selected=true]')?.textContent.trim()", 'Published');

    // Enter toggles membership rather than closing: a multi-select stays open
    // so more than one status can be picked without reopening it.
    $page->assertScript("document.activeElement.getAttribute('aria-label')", 'Search options');
});
