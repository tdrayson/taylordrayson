<?php

// Overlays/DatePicker.vue swaps its own contents between the day, month and
// year views, which detaches the header button mid-click. Its outside-click
// dismissal has to survive that.
it('drills from days to months to years without closing', function () {
    $filter = json_encode([[
        'type' => 'note',
        'conditions' => [['field' => 'day', 'operator' => 'on', 'value' => null]],
    ]]);

    $page = visit('/search?filter='.urlencode($filter))->resize(1280, 800);

    // The header naming the month and year is the popover's only bold control:
    // today's date is bold too, but at caption size.
    $header = '.shadow-card button.text-meta.font-semibold';

    $page->click('button:has-text("Pick a date")')
        ->assertScript("!! document.querySelector('.grid-cols-7')", true);

    $page->click($header)
        ->assertScript("document.querySelector('.grid-cols-3')?.textContent.includes('Jan')", true);

    $page->click($header)
        ->assertScript(
            "/^\\d{4} – \\d{4}$/.test(document.querySelector('.shadow-card span.font-semibold')?.textContent.trim())",
            true,
        );
});
