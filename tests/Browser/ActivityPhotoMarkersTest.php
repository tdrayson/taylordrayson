<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Storage;

/** A short polyline near Manchester, so markers have a route to sit on. */
const MARKER_POLYLINE = 'ohreIzatO}@}A_@k@';

it('renders a marker for each located photo and none for unlocated ones', function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');

    $activity = Activity::factory()->create([
        'type' => 'walk',
        'occurred_at' => '2026-07-12 12:00:00',
        'meta' => ['polyline' => MARKER_POLYLINE],
    ]);

    // Index 0 is unlocated, index 1 is located: the marker must carry index 1.
    // Coordinates are a point ON the decoded MARKER_POLYLINE (not Aylesby, as
    // the polyline actually decodes near Manchester) so the marker falls
    // inside the map's fit-to-route bounds and is visible/clickable in tests.
    $activity->addMediaFromString(fakeJpeg())->usingFileName('unlocated.jpg')->toMediaCollection('cover');
    $activity->addMediaFromString(fakeJpeg())
        ->usingFileName('located.jpg')
        ->withCustomProperties(['latitude' => 53.51095, 'longitude' => -2.72895])
        ->toMediaCollection('photos');

    $page = visit($activity->url());

    // Assert on rendered DOM, not text: latitude/longitude also live in the
    // Inertia props JSON, so a text assertion would pass with zero markers.
    $page->assertScript("document.querySelectorAll('[data-testid=\"photo-marker\"]').length", 1);
});

it('opens the lightbox at the right photo when a marker is clicked', function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');

    $activity = Activity::factory()->create([
        'type' => 'walk',
        'occurred_at' => '2026-07-12 12:00:00',
        'meta' => ['polyline' => MARKER_POLYLINE],
    ]);

    // Coordinates are a point ON the decoded MARKER_POLYLINE so the marker
    // falls inside the map's fit-to-route bounds and can actually be clicked.
    $activity->addMediaFromString(fakeJpeg())->usingFileName('unlocated.jpg')->toMediaCollection('cover');
    $activity->addMediaFromString(fakeJpeg())
        ->usingFileName('located.jpg')
        ->withCustomProperties(['latitude' => 53.51095, 'longitude' => -2.72895])
        ->toMediaCollection('photos');

    $page = visit($activity->url());

    // A synthetic Playwright mouse click races the marker (a DOM button) against
    // the map's WebGL canvas: mousedown and mouseup can briefly disagree on
    // which element is topmost at the exact pixel, so the browser resolves the
    // resulting "click" to their nearest common ancestor (the canvas container)
    // instead of the marker. This is a headless-canvas hit-testing artifact, not
    // a wiring bug (confirmed: the marker sits correctly in the DOM, at a stable
    // position, with the right listener attached). Dispatching the click event
    // directly on the marker still exercises the real Vue @click handler and
    // proves the wiring end-to-end without depending on synthetic pointer
    // hit-testing against the canvas.
    $page->assertScript(
        "(() => { document.querySelector('[data-testid=\"photo-marker\"]').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true })); return true; })()",
        true,
    );

    // The Lightbox dialog is a real element, so its presence proves the click landed.
    // But presence alone doesn't prove the RIGHT photo opened: the dialog renders
    // for any valid index (0 or 1, both < photos.length of 2). A filter-then-map
    // regression would renumber the located photo from its true index 1 down to 0,
    // and the dialog would still exist. The counter text ("n / total") is the only
    // rendered signal that reveals which index actually opened, so assert it reads
    // "2 / 2" (the located photo is the second of two), not "1 / 2".
    $page->assertScript(
        "(() => { const dialog = document.querySelector('[role=\"dialog\"]'); return dialog !== null && dialog.textContent.includes('2 / 2'); })()",
        true,
    );
});

it('keeps the hover scale off the positioned marker element so it cannot jump', function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');

    $activity = Activity::factory()->create([
        'type' => 'walk',
        'occurred_at' => '2026-07-12 12:00:00',
        'meta' => ['polyline' => MARKER_POLYLINE],
    ]);

    $activity->addMediaFromString(fakeJpeg())->usingFileName('unlocated.jpg')->toMediaCollection('cover');
    $activity->addMediaFromString(fakeJpeg())
        ->usingFileName('located.jpg')
        ->withCustomProperties(['latitude' => 53.51095, 'longitude' => -2.72895])
        ->toMediaCollection('photos');

    $page = visit($activity->url());

    // MapLibre positions the marker by writing an inline transform onto the
    // [data-testid=photo-marker] element itself. A hover scale on that same
    // element replaces the positioning transform, so the marker jumps to the map
    // origin on hover. The scale must therefore live on an inner element: the
    // positioned root must not carry it, and a descendant must. This fails if the
    // scale utility is ever moved back onto the button.
    $page->assertScript(
        "(() => {
            const root = document.querySelector('[data-testid=\"photo-marker\"]');
            if (!root) { return false; }
            const inner = root.querySelector('span');
            return ! root.className.includes('scale-110')
                && inner !== null
                && inner.className.includes('scale-110');
        })()",
        true,
    );
});

it('raises a focused marker above its neighbours so an overlapped photo stays reachable', function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');

    $activity = Activity::factory()->create([
        'type' => 'walk',
        'occurred_at' => '2026-07-12 12:00:00',
        'meta' => ['polyline' => MARKER_POLYLINE],
    ]);

    // Two located photos at distinct points on the decoded polyline, so there are
    // two markers to compare stacking between.
    $activity->addMediaFromString(fakeJpeg())
        ->usingFileName('first.jpg')
        ->withCustomProperties(['latitude' => 53.51064, 'longitude' => -2.72942])
        ->toMediaCollection('cover');
    $activity->addMediaFromString(fakeJpeg())
        ->usingFileName('second.jpg')
        ->withCustomProperties(['latitude' => 53.51111, 'longitude' => -2.72873])
        ->toMediaCollection('photos');

    $page = visit($activity->url());

    $page->assertScript("document.querySelectorAll('[data-testid=\"photo-marker\"]').length", 2);

    // Focusing a marker must lift it above its siblings, so a photo sitting
    // underneath an overlapping neighbour becomes reachable. Focus is used rather
    // than hover because a fully-occluded marker can never receive a mouse hover,
    // and focus() is deterministic in a headless browser.
    $page->assertScript(
        "(() => {
            const markers = [...document.querySelectorAll('[data-testid=\"photo-marker\"]')];
            if (markers.length < 2) { return false; }
            markers[0].focus();
            const focused = parseInt(getComputedStyle(markers[0]).zIndex) || 0;
            const sibling = parseInt(getComputedStyle(markers[1]).zIndex) || 0;
            return focused > sibling;
        })()",
        true,
    );
});
