<?php

use App\Actions\FetchStravaActivitySummaries;
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

it('pages until exhausted and keys summaries by string id', function () {
    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token']),
        '*/athlete/activities*' => Http::sequence()
            ->push([['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 2]])
            ->push([['id' => 200, 'start_date' => '2023-11-01T08:00:00Z', 'total_photo_count' => 0]])
            ->push([]),
    ]);

    $summaries = app(FetchStravaActivitySummaries::class)();

    expect($summaries)->toHaveKeys(['100', '200'])
        ->and($summaries['100']['start_date'])->toBe('2023-10-31T21:00:00Z')
        ->and($summaries['100']['total_photo_count'])->toBe(2)
        ->and($summaries['200']['total_photo_count'])->toBe(0);
});

it('returns null when a page request fails', function () {
    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token']),
        '*/athlete/activities*' => Http::response([], 500),
    ]);

    expect(app(FetchStravaActivitySummaries::class)())->toBeNull();
});

it('leaves start_date null when the summary has none', function () {
    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token']),
        '*/athlete/activities*' => Http::sequence()
            ->push([['id' => 777, 'total_photo_count' => 2]])
            ->push([]),
    ]);

    $summaries = app(FetchStravaActivitySummaries::class)();

    expect($summaries['777']['start_date'])->toBeNull();
});
