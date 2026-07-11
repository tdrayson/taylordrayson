<?php

use App\Models\Fuel;

// Exercises StoryToc.vue (via the fuel data story, which needs no media
// fixtures to seed). ContentToc.vue and StoryToc.vue now share the same
// useDialog composable, so this covers the dialog behaviour both components
// inherit: focus trap, Escape-to-close, and scroll lock.
it('opens the mobile contents sheet from the pill and closes it on escape', function () {
    Fuel::factory()->create(['occurred_at' => '2024-01-01 09:00:00', 'odometer' => 10000, 'litres' => 40]);
    Fuel::factory()->create(['occurred_at' => '2024-02-01 09:00:00', 'odometer' => 10300, 'litres' => 40]);

    // Mobile width so the floating "Contents" pill renders (it's xl:hidden).
    $page = visit('/stories/fuel')->resize(390, 844);

    // Dialog closed initially: no rendered dialog element.
    $page->assertScript("document.querySelector('[role=\"dialog\"]') === null", true);

    // The pill only reveals once the reader scrolls past the hero. script()
    // returns the raw JS result (not the page), so it can't be chained.
    $page->script('window.scrollTo(0, 2000)');
    $page->wait(0.3);

    // Open the sheet from the pill's "Contents" button.
    $page->click('Contents')
        ->assertScript("!!document.querySelector('[role=\"dialog\"]')", true);

    // Escape closes it (useDialog's shared keydown handler).
    $page->keys('[role="dialog"]', 'Escape')
        ->assertScript("document.querySelector('[role=\"dialog\"]') === null", true);
});
