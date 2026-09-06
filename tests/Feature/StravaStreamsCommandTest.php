<?php

use App\Models\Activity;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config(['services.strava.client_id' => 'x', 'services.strava.client_secret' => 'y', 'services.strava.refresh_token' => 'z']);
    Saloon::fake([
        'oauth/token*' => MockResponse::make(['access_token' => 'tok', 'expires_in' => 3600]),
        '/streams*' => MockResponse::make([
            'time' => ['data' => [0, 1]],
            'altitude' => ['data' => [10.0, 11.0]],
            'latlng' => ['data' => [[51.5, -0.1], [51.6, -0.2]]],
        ]),
    ]);
});

it('backfills streams for route-bearing activities and skips ones already done', function () {
    $withRoute = Activity::factory()->create(['source' => 'strava', 'source_id' => '1', 'meta' => ['polyline' => '_p~iF']]);
    $alreadyDone = Activity::factory()->create(['source' => 'strava', 'source_id' => '2', 'meta' => ['polyline' => 'abc'], 'altitude' => [['time' => 't', 'value' => 1]]]);

    $this->artisan('strava:streams')->assertSuccessful();

    expect($withRoute->fresh()->altitude)->not->toBeNull();
    // untouched (already had altitude, not forced)
    expect($alreadyDone->fresh()->altitude)->toHaveCount(1);
});
