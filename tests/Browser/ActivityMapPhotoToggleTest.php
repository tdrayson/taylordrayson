<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Storage;

it('hides and restores the map photo markers from the control', function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');

    $activity = Activity::factory()->create([
        'type' => 'walk',
        'occurred_at' => '2026-07-12 12:00:00',
        'meta' => ['polyline' => 'ohreIzatO}@}A_@k@'],
    ]);

    // A point on the decoded polyline, so the marker sits inside the fitted map.
    $activity->addMediaFromString(fakeJpeg())
        ->usingFileName('located.jpg')
        ->withCustomProperties(['latitude' => 53.51095, 'longitude' => -2.72895])
        ->toMediaCollection('photos');

    $page = visit($activity->url());

    // Hidden means gone from layout, not merely transparent: a marker left in
    // the tab order would still be reachable by keyboard.
    $page->click('[data-testid="toggle-photos"]')
        ->assertAttribute('[data-testid="toggle-photos"]', 'aria-pressed', 'false')
        ->assertScript(
            "document.querySelector('[data-testid=\"photo-marker\"]').offsetParent === null",
            true,
        );

    $page->click('[data-testid="toggle-photos"]')
        ->assertAttribute('[data-testid="toggle-photos"]', 'aria-pressed', 'true')
        ->assertScript(
            "document.querySelector('[data-testid=\"photo-marker\"]').offsetParent !== null",
            true,
        )
        ->assertNoJavascriptErrors();
});

it('offers no photo control on a route with no located photos', function () {
    $activity = Activity::factory()->create([
        'type' => 'walk',
        'occurred_at' => '2026-07-12 12:00:00',
        'meta' => ['polyline' => 'ohreIzatO}@}A_@k@'],
    ]);

    visit($activity->url())
        ->assertPresent('[data-testid="replay-route"]')
        ->assertMissing('[data-testid="toggle-photos"]');
});
