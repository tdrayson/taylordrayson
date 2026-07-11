<?php

use App\Models\Activity;

it('toggles distance and weight units from the settings modal', function () {
    // Desktop width so the sidebar gear is visible and its aria-labels resolve uniquely.
    $page = visit('/')->resize(1280, 800);

    $page->click('[aria-label="Open settings"]')
        ->assertScript("!!document.querySelector('[role=\"dialog\"]')", true);

    // Distance: default mi, switch to km, assert persisted + the native radio checked.
    $page->click('[aria-label="Distance unit"] [aria-label="km"]')
        ->assertScript("localStorage.getItem('pref:distanceUnit')", 'km')
        ->assertScript(
            "document.querySelector('[aria-label=\"Distance unit\"] input[value=\"km\"]').checked",
            true,
        );

    // Weight: default kg, switch to lbs.
    $page->click('[aria-label="Weight unit"] [aria-label="lbs"]')
        ->assertScript("localStorage.getItem('pref:weightUnit')", 'lbs')
        ->assertScript(
            "document.querySelector('[aria-label=\"Weight unit\"] input[value=\"lbs\"]').checked",
            true,
        );
});

it('updates rendered distance and weight live when units change', function () {
    // 5000 m -> 3.1 mi / 5.0 km; a 100 kg set -> 220.5 lbs.
    $activity = Activity::factory()->create([
        'name' => 'Units Run',
        'type' => 'gym',
        'distance' => 5000,
        'duration' => 1800,
        'occurred_at' => '2026-03-15 07:30:00',
        'meta' => ['sets' => [['exercise' => 'Bench Press', 'reps' => 5, 'weight_kg' => 100]]],
    ]);

    $url = '/'.$activity->occurred_at->format('Y/m/d').'/'.$activity->slug();
    $page = visit($url)->resize(1280, 800);

    // Distance stat unit starts as mi (rendered in a StatGrid <abbr>).
    $page->assertScript(
        "[...document.querySelectorAll('abbr')].some(a => a.textContent.trim() === 'mi')",
        true,
    );

    // Open settings and switch distance to km; the <abbr> updates without reload.
    $page->click('[aria-label="Open settings"]')
        ->click('[aria-label="Distance unit"] [aria-label="km"]')
        ->assertScript(
            "[...document.querySelectorAll('abbr')].some(a => a.textContent.trim() === 'km')",
            true,
        )
        ->assertScript(
            "[...document.querySelectorAll('abbr')].some(a => a.textContent.trim() === 'mi')",
            false,
        );

    // Switch weight to lbs; the set weight text flips from kg to lbs without reload.
    $page->click('[aria-label="Weight unit"] [aria-label="lbs"]')
        ->assertScript("document.body.innerText.includes('lbs')", true)
        ->assertScript("document.body.innerText.includes(' kg')", false);
});

it('reformats timeline card subtitles live when distance unit changes', function () {
    Activity::factory()->create([
        'name' => 'Card Run',
        'type' => 'run',
        'distance' => 5000, // 3.1 mi / 5.0 km
        'duration' => 1800,
        'occurred_at' => '2026-03-15 07:30:00',
    ]);

    // The day page renders that day's timeline feed (FeedItem cards). Scope the
    // assertion to the card's own subtitle paragraph (.p-summary inside
    // .timeline-feed) rather than the whole page: the day page's summary stat
    // block also renders a hardcoded, non-reactive "mi" unit <abbr>, which would
    // make a body-wide 'mi'/'km' check a false positive/negative.
    $page = visit('/2026/03/15')->resize(1280, 800);

    // Card subtitle starts in miles.
    $page->assertScript(
        "[...document.querySelectorAll('.timeline-feed .p-summary')].some(p => p.textContent.includes('mi'))",
        true,
    );

    $page->click('[aria-label="Open settings"]')
        ->click('[aria-label="Distance unit"] [aria-label="km"]')
        ->assertScript(
            "[...document.querySelectorAll('.timeline-feed .p-summary')].some(p => p.textContent.includes('km'))",
            true,
        )
        ->assertScript(
            "[...document.querySelectorAll('.timeline-feed .p-summary')].some(p => p.textContent.includes('mi'))",
            false,
        );
});

it('reformats year timeline aggregate stats live when distance unit changes', function () {
    // Server now sends raw distanceM + precision for these StatGrid stats
    // (TimelineController::periodStats); the client formats them via useFormat.
    Activity::factory()->create([
        'name' => 'Year Run',
        'type' => 'run',
        'distance' => 8047, // 5.0 mi / 8.0 km
        'duration' => 1800,
        'occurred_at' => '2026-06-01 07:30:00',
    ]);
    Activity::factory()->create([
        'name' => 'Year Ride',
        'type' => 'ride',
        'distance' => 16093, // 10 mi / 16.1 km
        'duration' => 3600,
        'occurred_at' => '2026-06-02 07:30:00',
    ]);

    $page = visit('/2026')->resize(1280, 800);

    // Scope to the year page's own StatGrid (rendered with class "mt-8") so a
    // reactive <abbr> from the deferred timeline feed cards below it can't
    // produce a false positive/negative on the same page.
    $page->assertScript(
        "[...document.querySelectorAll('dl.mt-8 abbr')].some(a => a.textContent.trim() === 'mi')",
        true,
    );

    $page->click('[aria-label="Open settings"]')
        ->click('[aria-label="Distance unit"] [aria-label="km"]')
        ->assertScript(
            "[...document.querySelectorAll('dl.mt-8 abbr')].some(a => a.textContent.trim() === 'km')",
            true,
        )
        ->assertScript(
            "[...document.querySelectorAll('dl.mt-8 abbr')].some(a => a.textContent.trim() === 'mi')",
            false,
        );
});

it('hides a year aggregate distance stat that rounds to zero, in both units', function () {
    // A single 400m walk is the only "Walked" activity for the year: at
    // precision 0 that's 0.25 mi / 0.4 km, i.e. it rounds to "0" in either
    // unit. StatGrid must hide the stat rather than render the misleading
    // "0 mi" (the server used to only hide it when raw metres was exactly 0,
    // so a small nonzero total like this previously rendered as "0 mi").
    Activity::factory()->create([
        'name' => 'Tiny Walk',
        'type' => 'walk',
        'distance' => 400,
        'duration' => 300,
        'occurred_at' => '2026-06-01 07:30:00',
    ]);

    $page = visit('/2026')->resize(1280, 800);

    // Scope to the year page's own StatGrid (rendered with class "mt-8"), same
    // as the reactive-aggregate test above, so the deferred timeline feed
    // below it can't produce a false positive/negative.
    $page->assertScript(
        "[...document.querySelectorAll('dl.mt-8 dd')].every(dd => !dd.textContent.trim().includes('0 mi'))",
        true,
    );

    $page->click('[aria-label="Open settings"]')
        ->click('[aria-label="Distance unit"] [aria-label="km"]')
        ->assertScript(
            "[...document.querySelectorAll('dl.mt-8 dd')].every(dd => !dd.textContent.trim().includes('0 km'))",
            true,
        );
});

it('reformats stats-page metric cards live when distance unit changes', function () {
    // Server now sends raw distanceM + precision for distance metrics
    // (StatsController::metrics); MetricCard formats them via useFormat.
    Activity::factory()->create([
        'name' => 'Stats Run',
        'type' => 'run',
        'distance' => 8047, // 5.0 mi / 8.0 km
        'duration' => 1800,
        'occurred_at' => '2026-03-15 07:30:00',
    ]);

    $page = visit('/stats/activities')->resize(1280, 800);

    $page->assertScript(
        "[...document.querySelectorAll('abbr')].some(a => a.textContent.trim() === 'mi')",
        true,
    );

    $page->click('[aria-label="Open settings"]')
        ->click('[aria-label="Distance unit"] [aria-label="km"]')
        ->assertScript(
            "[...document.querySelectorAll('abbr')].some(a => a.textContent.trim() === 'km')",
            true,
        )
        ->assertScript(
            "[...document.querySelectorAll('abbr')].some(a => a.textContent.trim() === 'mi')",
            false,
        );
});
