<?php

use App\Models\Activity;
use App\Models\Place;
use Illuminate\Support\Facades\Storage;

/** Whatever paints at the element's centre must not belong to it. */
const HIT_TEST = '(() => {
    const el = document.querySelector(SELECTOR);
    const box = el.getBoundingClientRect();
    const top = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);

    return top?.closest(SELECTOR) === null;
})()';

/**
 * A script asserting the element is painted over while the lightbox is open.
 *
 * @param  string  $selector  CSS selector for the element that must be covered.
 */
function hitTestCovered(string $selector): string
{
    return str_replace('SELECTOR', "'{$selector}'", HIT_TEST);
}

it('draws the lightbox above the map photo markers', function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');

    $activity = Activity::factory()->create([
        'type' => 'walk',
        'occurred_at' => '2026-07-12 12:00:00',
        'meta' => ['polyline' => 'ohreIzatO}@}A_@k@'],
    ]);

    $activity->addMediaFromString(fakeJpeg())
        ->usingFileName('located.jpg')
        ->withCustomProperties(['latitude' => 53.51095, 'longitude' => -2.72895])
        ->toMediaCollection('photos');

    $page = visit($activity->url())->resize(1280, 900);

    $page->click('[data-testid="photo-marker"]')
        ->assertPresent('[role="dialog"]')
        // Present is not the same as painted: the overlay fades in over 0.2s
        // and a hit-test run mid-fade still finds the marker underneath, which
        // is what failed under a loaded suite. Waited rather than asserted,
        // because assertScript evaluates once and would race the same way.
        ->wait(0.5)
        ->assertScript(
            "getComputedStyle(document.querySelector('[role=\"dialog\"]')).opacity === '1'",
            true,
        );

    // Clicking a marker focuses it, and focus is what lifts it up the stack.
    $page->assertScript(hitTestCovered('[data-testid="photo-marker"]'), true);

    // Same test for maplibre's own zoom control, which vendor.css lifts to
    // z-index 901 and which sits in the same contained stacking context.
    $page->assertScript(hitTestCovered('.maplibregl-ctrl-top-right'), true);
});

it('draws the lightbox above the zoom control of a plain location map', function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');

    $place = Place::factory()->create([
        'occurred_at' => '2026-07-12 12:00:00',
        'latitude' => 51.31345,
        'longitude' => -0.08194,
    ]);

    $place->addMediaFromString(fakeJpeg())->usingFileName('lunch.jpg')->toMediaCollection('photos');

    $page = visit($place->url())->resize(1280, 900);

    $page->click('[aria-label="View photo 1"]')
        ->assertPresent('[role="dialog"]')
        ->wait(0.5)
        ->assertScript(hitTestCovered('.maplibregl-ctrl-top-right'), true);
});
