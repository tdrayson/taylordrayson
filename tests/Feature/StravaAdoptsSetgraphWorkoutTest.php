<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.strava.client_id' => 'x', 'services.strava.client_secret' => 'y', 'services.strava.refresh_token' => 'z']);

    $summary = [
        'id' => 19532725079,
        'name' => 'Evening Weight Training',
        'sport_type' => 'WeightTraining',
        'start_date_local' => '2026-07-27T19:49:17Z',
        'moving_time' => 2311,
        'elapsed_time' => 2311,
        'distance' => 0,
        'calories' => 300,
        'average_heartrate' => 118,
        'max_heartrate' => 154,
        'total_photo_count' => 0,
        'timezone' => '(GMT+00:00) Europe/London',
    ];

    Http::fake([
        '*oauth/token*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        '*athlete/activities*' => Http::sequence()
            ->push([$summary])
            ->push([]),
        '*activities/19532725079/streams*' => Http::response([]),
        '*activities/19532725079*' => Http::response($summary),
    ]);
});

it('adopts the Setgraph workout instead of creating a second activity', function () {
    // Setgraph only knows when the workout was shared, so the placeholder sits
    // 21 minutes after the session actually began.
    $setgraph = Activity::factory()->create([
        'occurred_at' => '2026-07-27 20:10:00',
        'type' => 'weight-training',
        'name' => 'Weight Training',
        'duration' => 2280,
        'source' => 'setgraph',
        'source_id' => null,
        'average_heart_rate' => null,
        'meta' => ['sets' => [['exercise' => 'Lat Pulldown', 'reps' => 12, 'weight_kg' => 32]]],
    ]);

    $this->artisan('strava:sync')->assertSuccessful();

    $setgraph->refresh();

    expect(Activity::count())->toBe(1)
        ->and($setgraph->source)->toBe('strava')
        ->and($setgraph->source_id)->toBe('19532725079')
        // Strava's richer figures land on the row Setgraph opened, including
        // the real start time in place of the share-time estimate.
        ->and($setgraph->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-07-27 19:49:17')
        ->and($setgraph->name)->toBe('Evening Weight Training')
        ->and($setgraph->average_heart_rate)->toEqual(118)
        ->and($setgraph->duration)->toBe(2311)
        // The sets Setgraph logged survive the adoption.
        ->and($setgraph->meta['sets'])->toHaveCount(1)
        ->and($setgraph->meta['sport_type'])->toBe('WeightTraining');
});

it('still creates an activity when no Setgraph workout is waiting', function () {
    $this->artisan('strava:sync')->assertSuccessful();

    expect(Activity::count())->toBe(1)
        ->and(Activity::sole()->source)->toBe('strava');
});

it('leaves a Setgraph workout from another day alone', function () {
    Activity::factory()->create([
        'occurred_at' => '2026-07-20 19:49:00',
        'type' => 'weight-training',
        'source' => 'setgraph',
        'source_id' => null,
        'meta' => ['sets' => []],
    ]);

    $this->artisan('strava:sync')->assertSuccessful();

    expect(Activity::count())->toBe(2);
});
