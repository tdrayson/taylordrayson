<?php

use App\Models\Activity;
use App\Models\Calorie;
use App\Models\Sleep;

use function Pest\Laravel\get;

it('wires month roll-up stats and per-day calendar data', function () {
    Activity::factory()->create(['type' => 'run', 'distance' => 5000, 'occurred_at' => '2026-06-10 07:00:00']);
    Activity::factory()->create(['type' => 'run', 'distance' => 8000, 'occurred_at' => '2026-06-15 07:00:00']);
    Sleep::factory()->create(['duration' => 25920, 'occurred_at' => '2026-06-10 06:30:00']);
    Calorie::factory()->create(['calories' => 600, 'occurred_at' => '2026-06-10 13:00:00']);

    get('/2026/06')->assertInertia(fn ($page) => $page
        ->component('Month')
        ->where('entriesCount', fn ($count) => $count >= 3)
        ->where('days.10.types', fn ($types) => collect($types)->contains('activity'))
        ->where('days.10.types', fn ($types) => ! collect($types)->contains('calorie'))
        ->where('days.10.sleep', 25920)
        ->where('days.10.calories', 600)
        ->where('stats', fn ($stats) => collect($stats)->pluck('label')->contains('Activities'))
    );
});

it('loads the day feed and summary stats from the database', function () {
    Activity::factory()->create(['name' => 'Morning Run', 'type' => 'run', 'distance' => 5000, 'occurred_at' => '2026-06-21 07:30:00']);
    Sleep::factory()->create(['duration' => 25200, 'occurred_at' => '2026-06-21 06:30:00']);
    Calorie::factory()->create(['calories' => 600, 'occurred_at' => '2026-06-21 13:00:00']);

    get('/2026/06/21')->assertInertia(fn ($page) => $page
        ->component('Day')
        ->has('items', 3)
        ->where('items.0.iconKey', fn ($key) => in_array($key, ['activity', 'sleep', 'calorie'], true))
        ->where('stats', fn ($stats) => collect($stats)->pluck('label')->contains('Slept'))
    );
});

it('orders the day feed chronologically, earliest first', function () {
    Activity::factory()->create(['name' => 'Evening Run', 'occurred_at' => '2026-06-21 20:00:00']);
    Activity::factory()->create(['name' => 'Morning Run', 'occurred_at' => '2026-06-21 07:00:00']);

    get('/2026/06/21')->assertInertia(fn ($page) => $page
        ->where('items.0.title', 'Morning Run')
        ->where('items.1.title', 'Evening Run')
    );
});

it('shows an empty day with no items', function () {
    get('/2026/06/21')->assertInertia(fn ($page) => $page->component('Day')->has('items', 0)->has('stats', 0));
});
