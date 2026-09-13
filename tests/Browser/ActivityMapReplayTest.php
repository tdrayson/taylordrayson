<?php

use App\Models\Activity;

/**
 * A meandering route near Manchester, 60 points.
 *
 * Deliberately not a three-point line: the draw runs at a steady on-screen
 * speed with a 2.2s floor, so a short route replays in the minimum and the
 * window in which the control reads "Pause" is 2.2s wide. Under a loaded suite
 * the assertions landed after the draw had finished and the label had flipped
 * back. A longer line draws for nearer the 5.5s ceiling.
 */
const REPLAY_POLYLINE = '_xleIbrtL_IcL~CcL~CcL_IcL_IcL~CcL~CcL_IcL_IcL~CcL~CcL_IcL_IcL~CcL~CcL_IcL_IcL~CcL~CcL_IcL_IcL~CcL~CcL_IcL_IcL~CcL~CcL_IcL_IcL~CcL~CcL_IcL_IcL~CcL~CcL_IcL_IcL~CcL~CcL_IcL_IcL~CcL~CcL_IcL_IcL~CcL~CcL_IcL_IcL~CcL~CcL_IcL_IcL~CcL~CcL';

it('replays the route draw from the map control, and pauses it', function () {
    $activity = Activity::factory()->create([
        'type' => 'walk',
        'occurred_at' => '2026-07-12 12:00:00',
        'meta' => ['polyline' => REPLAY_POLYLINE],
    ]);

    $page = visit($activity->url());

    // The intro finishes on its own, leaving the control offering a replay.
    $page->assertPresent('[data-testid="replay-route"]')
        ->assertAttribute('[data-testid="replay-route"]', 'aria-label', 'Replay the route');

    // Pressing it starts the draw again: the button becomes a pause, and the
    // head dot that only exists mid-draw is back on the map.
    $page->click('[data-testid="replay-route"]')
        ->assertAttribute('[data-testid="replay-route"]', 'aria-label', 'Pause the route replay')
        ->assertScript("!!document.querySelector('[data-testid=\"draw-head\"]')", true);

    // Pausing holds the line where it is rather than completing or resetting it.
    $page->click('[data-testid="replay-route"]')
        ->assertAttribute('[data-testid="replay-route"]', 'aria-label', 'Replay the route')
        ->assertNoJavascriptErrors();
});
