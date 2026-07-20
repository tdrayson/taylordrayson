<?php

use App\Actions\StoreActivityStreams;
use App\Models\Activity;
use App\Services\Strava;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.strava.client_id' => 'x', 'services.strava.client_secret' => 'y', 'services.strava.refresh_token' => 'z']);
});

it('stores aligned absolute-time streams on the activity', function () {
    // Http::fake() re-encodes array bodies via a plain json_encode(), which
    // drops the trailing zero on whole-number floats (10.0 -> "10"), unlike
    // Strava's real JSON responses. Pass a pre-encoded string body (with
    // JSON_PRESERVE_ZERO_FRACTION) so the fake round-trips floats the same
    // way the live API does.
    Http::fake([
        '*oauth/token*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        '*/streams*' => Http::response(json_encode([
            'time' => ['data' => [0, 1, 2]],
            'altitude' => ['data' => [10.0, 11.0, 12.0]],
            'velocity_smooth' => ['data' => [2.0, 2.5, 3.0]],
            'latlng' => ['data' => [[51.5, -0.1], [51.6, -0.2], [51.7, -0.3]]],
            'heartrate' => ['data' => [120, 130, 140]],
        ], JSON_PRESERVE_ZERO_FRACTION)),
    ]);
    $activity = Activity::factory()->create(['source_id' => '999', 'occurred_at' => '2024-01-01 08:00:00']);

    $stored = app(StoreActivityStreams::class)($activity->fresh(), app(Strava::class));

    expect($stored)->toBeTrue();
    $activity->refresh();
    expect($activity->altitude)->toHaveCount(3);
    expect($activity->altitude[0])->toBe(['time' => '2024-01-01 08:00:00', 'value' => 10.0]);
    expect($activity->speed[2]['value'])->toBe(3.0);
    expect($activity->track[1])->toBe(['time' => '2024-01-01 08:00:01', 'lat' => 51.6, 'lng' => -0.2]);
    expect($activity->heart_rate[0])->toBe(['time' => '2024-01-01 08:00:00', 'bpm' => 120]);
});

it('returns false and stores nothing when Strava has no streams', function () {
    Http::fake([
        '*oauth/token*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        '*/streams*' => Http::response([]),
    ]);
    $activity = Activity::factory()->create(['source_id' => '998']);

    expect(app(StoreActivityStreams::class)($activity, app(Strava::class)))->toBeFalse();
    expect($activity->fresh()->altitude)->toBeNull();
});
