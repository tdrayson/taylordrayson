<?php

use App\Services\Strava;
use Illuminate\Support\Facades\Cache;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config([
        'services.strava.client_id' => 'cid',
        'services.strava.client_secret' => 'secret',
        'services.strava.refresh_token' => 'refresh',
    ]);
    Cache::flush();
});

it('requests the time and latlng streams keyed by type', function () {
    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token']),
        '/streams*' => MockResponse::make([
            'time' => ['data' => [0, 1, 2]],
            'latlng' => ['data' => [[51.1, -0.1], [51.2, -0.2], [51.3, -0.3]]],
        ]),
    ]);

    $streams = app(Strava::class)->activityStreams(123);

    expect($streams['time']['data'])->toBe([0, 1, 2])
        ->and($streams['latlng']['data'][0])->toBe([51.1, -0.1]);

    // getUrl() is the path only; Saloon keeps the query string separate.
    Saloon::assertSent(function ($request, $response) {
        $query = $request->query()->all();

        return str_contains($response->getPendingRequest()->getUrl(), '/activities/123/streams')
            && $query['keys'] === 'time,latlng'
            && $query['key_by_type'] === 'true';
    });
});

it('returns null when the streams request fails', function () {
    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token']),
        '/streams*' => MockResponse::make([], 500),
    ]);

    expect(app(Strava::class)->activityStreams(123))->toBeNull();
});
