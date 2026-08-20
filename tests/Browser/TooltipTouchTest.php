<?php

use App\Models\Sleep;
use Illuminate\Support\Carbon;

function seedNights(): void
{
    Carbon::setTestNow('2026-08-20 09:00:00');

    foreach (range(0, 6) as $back) {
        Sleep::factory()->create([
            'occurred_at' => Carbon::parse('2026-08-20')->subDays($back)->startOfDay(),
            'duration' => 7 * 3600,
        ]);
    }
}

it('shows a tooltip on a link where the pointer can hover', function () {
    seedNights();

    $page = visit('/now')->resize(1280, 900);

    $page->hover('.sleep__bars > span:nth-child(2)')
        ->assertScript("document.querySelector('[role=\"tooltip\"]') !== null", true);
});

it('keeps the tooltip down on a link when the pointer cannot hover', function () {
    seedNights();

    $page = visit('/now')->resize(1280, 900);

    // Emulated rather than assumed: the component reads (hover: hover), and a
    // desktop browser answers yes however the event was produced.
    $page->script("
        window.matchMedia = (query) => ({
            matches: ! String(query ?? '').includes('hover: hover'),
            addEventListener() {}, removeEventListener() {},
        });
    ");

    $page->hover('.sleep__bars > span:nth-child(2)')
        ->assertScript("document.querySelector('[role=\"tooltip\"]') === null", true);
});

it('still shows a tooltip on a non-link when the pointer cannot hover', function () {
    // A night with no record draws no bar, so its column is a plain span with
    // no link either inside it or around it: a tap there means nothing else.
    Carbon::setTestNow('2026-08-20 09:00:00');

    $page = visit('/now')->resize(1280, 900);

    $page->script("
        window.matchMedia = (query) => ({
            matches: ! String(query ?? '').includes('hover: hover'),
            addEventListener() {}, removeEventListener() {},
        });
    ");

    $page->hover('.sleep__bars > span:nth-child(2)')
        ->assertScript("document.querySelector('[role=\"tooltip\"]') !== null", true);
});

it('suppresses a tooltip that sits inside a link, not only one that wraps one', function () {
    seedNights();

    $page = visit('/now')->resize(1280, 900);

    $page->script("
        window.matchMedia = (query) => ({
            matches: ! String(query ?? '').includes('hover: hover'),
            addEventListener() {}, removeEventListener() {},
        });
    ");

    // The top bar's weather tile is a tooltip wrapped in a link to /now, the
    // other nesting to the sleep bars above.
    $page->hover('.weather-status:visible')
        ->assertScript("document.querySelector('[role=\"tooltip\"]') === null", true);
});
