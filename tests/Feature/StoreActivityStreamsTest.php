<?php

use App\Actions\StoreActivityStreams;
use App\Models\Activity;
use App\Services\Strava\Client;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config(['services.strava.client_id' => 'x', 'services.strava.client_secret' => 'y', 'services.strava.refresh_token' => 'z']);
});

it('stores aligned absolute-time streams on the activity', function () {
    // Saloon::fake(['' => MockResponse::make('', 200)]) re-encodes array bodies via a plain json_encode(), which
    // drops the trailing zero on whole-number floats (10.0 -> "10"), unlike
    // Strava's real JSON responses. Pass a pre-encoded string body (with
    // JSON_PRESERVE_ZERO_FRACTION) so the fake round-trips floats the same
    // way the live API does.
    Saloon::fake([
        'oauth/token*' => MockResponse::make(['access_token' => 'tok', 'expires_in' => 3600]),
        '/streams*' => MockResponse::make(json_encode([
            'time' => ['data' => [0, 1, 2]],
            'altitude' => ['data' => [10.0, 11.0, 12.0]],
            'velocity_smooth' => ['data' => [2.0, 2.5, 3.0]],
            'latlng' => ['data' => [[51.5, -0.1], [51.6, -0.2], [51.7, -0.3]]],
            'heartrate' => ['data' => [120, 130, 140]],
        ], JSON_PRESERVE_ZERO_FRACTION)),
    ]);
    $activity = Activity::factory()->create(['source_id' => '999', 'occurred_at' => '2024-01-01 08:00:00']);

    $stored = app(StoreActivityStreams::class)($activity->fresh(), app(Client::class));

    expect($stored)->toBeTrue();
    $activity->refresh();
    expect($activity->altitude)->toHaveCount(3);
    expect($activity->speed[2]['value'])->toBe(3.0);

    // toEqual, not toBe: MySQL stores JSON in a binary form that reorders object
    // keys (shortest first), so an identical-array check compares key order and
    // fails there while passing on SQLite. Nothing reads a point by key order.
    expect($activity->altitude[0])->toEqual(['time' => '2024-01-01 08:00:00', 'value' => 10.0])
        ->and($activity->track[1])->toEqual(['time' => '2024-01-01 08:00:01', 'lat' => 51.6, 'lng' => -0.2])
        ->and($activity->heart_rate[0])->toEqual(['time' => '2024-01-01 08:00:00', 'bpm' => 120]);
});

it('returns false and stores nothing when Strava has no streams', function () {
    Saloon::fake([
        'oauth/token*' => MockResponse::make(['access_token' => 'tok', 'expires_in' => 3600]),
        '/streams*' => MockResponse::make([]),
    ]);
    $activity = Activity::factory()->create(['source_id' => '998']);

    expect(app(StoreActivityStreams::class)($activity, app(Client::class)))->toBeFalse();
    expect($activity->fresh()->altitude)->toBeNull();
});
