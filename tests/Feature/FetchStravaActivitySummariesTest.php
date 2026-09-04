<?php

use App\Actions\FetchStravaActivitySummaries;
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

it('pages until exhausted and keys summaries by string id', function () {
    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token']),
        '/athlete/activities*' => mockSequence([
            MockResponse::make([['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 2]]),
            MockResponse::make([['id' => 200, 'start_date' => '2023-11-01T08:00:00Z', 'total_photo_count' => 0]]),
        ]),
    ]);

    $summaries = app(FetchStravaActivitySummaries::class)();

    expect($summaries)->toHaveKeys(['100', '200'])
        ->and($summaries['100']['start_date'])->toBe('2023-10-31T21:00:00Z')
        ->and($summaries['100']['total_photo_count'])->toBe(2)
        ->and($summaries['200']['total_photo_count'])->toBe(0);
});

it('returns null when a page request fails', function () {
    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token']),
        '/athlete/activities*' => MockResponse::make([], 500),
    ]);

    expect(app(FetchStravaActivitySummaries::class)())->toBeNull();
});

it('leaves start_date null when the summary has none', function () {
    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token']),
        '/athlete/activities*' => mockSequence([
            MockResponse::make([['id' => 777, 'total_photo_count' => 2]]),
        ]),
    ]);

    $summaries = app(FetchStravaActivitySummaries::class)();

    expect($summaries['777']['start_date'])->toBeNull();
});
