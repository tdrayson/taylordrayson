<?php

use App\Models\Activity;
use App\Models\Calorie;
use App\Models\Concerns\Timelineable;
use App\Models\Sleep;

use function Pest\Laravel\get;

function entryUrl(Timelineable $model): string
{
    return $model->occurred_at->format('Y/m/d').'/'.$model->slug();
}

it('renders an activity entry via Inertia', function () {
    $activity = Activity::factory()->create([
        'name' => 'Morning Run',
        'type' => 'run',
        'distance_km' => 5.42,
        'duration' => 2340,
        'occurred_at' => '2026-03-15 07:30:00',
    ]);

    get('/'.entryUrl($activity))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Entry')
            ->where('type', 'activity')
            ->where('accent', 'activity')
            ->where('title', 'Morning Run')
            ->where('entry.distance_km', fn ($value) => (float) $value === 5.42)
        );
});

it('exposes the polyline when present', function () {
    $activity = Activity::factory()->create([
        'name' => 'Mapped Run',
        'type' => 'run',
        'occurred_at' => '2026-03-15 07:30:00',
        'meta' => ['polyline' => 'abc123', 'elevation_gain' => 40],
    ]);

    get('/'.entryUrl($activity))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Entry')->where('polyline', 'abc123'));
});

it('cites the source with a link back to the platform', function () {
    $activity = Activity::factory()->create([
        'name' => 'Strava Run',
        'type' => 'run',
        'platform_type' => 'strava',
        'platform_id' => '12345',
        'occurred_at' => '2026-03-15 07:30:00',
    ]);

    get('/'.entryUrl($activity))->assertInertia(fn ($page) => $page
        ->where('source.platform', 'strava')
        ->where('source.url', 'https://www.strava.com/activities/12345')
    );
});

it('cites a source without a link when the platform has no url', function () {
    $sleep = Sleep::factory()->create(['source' => 'oura', 'occurred_at' => '2026-03-15 06:30:00']);

    get('/'.entryUrl($sleep))->assertInertia(fn ($page) => $page
        ->where('source.platform', 'oura')
        ->where('source.url', null)
    );
});

it('aggregates the whole day for a food entry', function () {
    Calorie::factory()->create(['meal' => 'breakfast', 'calories' => 320, 'occurred_at' => '2026-03-15 08:00:00']);
    $lunch = Calorie::factory()->create(['meal' => 'lunch', 'calories' => 450, 'occurred_at' => '2026-03-15 13:00:00']);

    get('/'.entryUrl($lunch))->assertInertia(fn ($page) => $page
        ->component('Entry')
        ->where('type', 'calorie')
        ->where('entry.totals.calories', 770)
        ->has('entry.meals', 2)
    );
});

it('returns 404 for an unknown entry slug', function () {
    Activity::factory()->create([
        'name' => 'Morning Run',
        'occurred_at' => '2026-03-15 07:30:00',
    ]);

    get('/2026/03/15/does-not-exist')->assertNotFound();
});
