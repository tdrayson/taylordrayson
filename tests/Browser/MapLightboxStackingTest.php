<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Storage;

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
        ->assertPresent('[role="dialog"]');

    // Hit-test the marker's own centre: whatever paints there while the
    // lightbox is open must belong to the lightbox, not the map. Clicking a
    // marker focuses it, and focus is what lifts it up the stack.
    $page->assertScript(
        "(() => {
            const marker = document.querySelector('[data-testid=\"photo-marker\"]');
            const box = marker.getBoundingClientRect();
            const top = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);

            return top?.closest('[data-testid=\"photo-marker\"]') === null;
        })()",
        true,
    );
});
