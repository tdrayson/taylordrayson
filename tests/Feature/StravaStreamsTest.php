<?php

use App\Services\Strava;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.strava.client_id' => 'cid',
        'services.strava.client_secret' => 'secret',
        'services.strava.refresh_token' => 'refresh',
    ]);
    Cache::flush();
});

it('requests the time and latlng streams keyed by type', function () {
    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token']),
        '*/streams*' => Http::response([
            'time' => ['data' => [0, 1, 2]],
            'latlng' => ['data' => [[51.1, -0.1], [51.2, -0.2], [51.3, -0.3]]],
        ]),
    ]);

    $streams = app(Strava::class)->activityStreams(123);

    expect($streams['time']['data'])->toBe([0, 1, 2])
        ->and($streams['latlng']['data'][0])->toBe([51.1, -0.1]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/activities/123/streams')
            && str_contains($request->url(), 'keys=time%2Clatlng')
            && str_contains($request->url(), 'key_by_type=true');
    });
});

it('returns null when the streams request fails', function () {
    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token']),
        '*/streams*' => Http::response([], 500),
    ]);

    expect(app(Strava::class)->activityStreams(123))->toBeNull();
});
