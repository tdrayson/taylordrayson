<?php

use App\Actions\Strava\FetchStravaActivitySummaries;
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

// A short page is the last page. Paging on until an empty one came back spent
// a second read on every single run, against a 1,000-read daily budget.
it('pages while a page comes back full and stops on a short one', function () {
    $full = collect(range(1, 200))
        ->map(fn (int $i): array => ['id' => $i, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 2])
        ->all();

    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token']),
        '/athlete/activities*' => mockSequence([
            MockResponse::make($full),
            MockResponse::make([['id' => 900, 'start_date' => '2023-11-01T08:00:00Z', 'total_photo_count' => 0]]),
        ]),
    ]);

    $summaries = app(FetchStravaActivitySummaries::class)();

    expect($summaries)->toHaveCount(201)
        ->toHaveKeys(['1', '200', '900'])
        ->and($summaries['1']['start_date'])->toBe('2023-10-31T21:00:00Z')
        ->and($summaries['1']['total_photo_count'])->toBe(2)
        ->and($summaries['900']['total_photo_count'])->toBe(0);
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
        '/athlete/activities*' => MockResponse::make([['id' => 777, 'total_photo_count' => 2]]),
    ]);

    $summaries = app(FetchStravaActivitySummaries::class)();

    expect($summaries['777']['start_date'])->toBeNull();
});
