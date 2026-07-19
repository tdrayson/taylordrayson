<?php

use App\Models\Activity;
use App\Models\Note;

use function Pest\Laravel\get;

it('exposes a deferred profile prop for an activity with streams', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => now(),
        'altitude' => [['time' => '2024-01-01 00:00:00', 'value' => 10]],
        'track' => [['time' => '2024-01-01 00:00:00', 'lat' => 51.5, 'lng' => -0.1]],
    ]);

    // loadDeferredProps() replays the initial visit as a partial reload with
    // the correct X-Inertia-Version and X-Inertia-Partial-Data headers, which
    // is how Inertia's own test suite resolves deferred props.
    get($activity->url())->assertInertia(fn ($page) => $page
        ->component('Entry')
        ->loadDeferredProps(fn ($page) => $page
            ->where('profile.altitude.0.value', 10)
        )
    );
});

it('excludes the raw stream series from the main entry payload', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => now(),
        'heart_rate' => [['time' => '2024-01-01 00:00:00', 'value' => 140]],
        'altitude' => [['time' => '2024-01-01 00:00:00', 'value' => 10]],
        'speed' => [['time' => '2024-01-01 00:00:00', 'value' => 3.2]],
        'track' => [['time' => '2024-01-01 00:00:00', 'lat' => 51.5, 'lng' => -0.1]],
    ]);

    get($activity->url())->assertInertia(fn ($page) => $page
        ->component('Entry')
        ->missing('entry.heart_rate')
        ->missing('entry.altitude')
        ->missing('entry.speed')
        ->missing('entry.track')
    );
});

it('does not expose a profile prop for non-activity entries', function () {
    $note = Note::factory()->create(['occurred_at' => now()]);

    get($note->url())->assertInertia(fn ($page) => $page
        ->component('Entry')
        ->where('profile', null)
    );
});
