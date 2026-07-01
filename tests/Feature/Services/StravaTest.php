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

it('refreshes and caches the access token, reusing it across calls', function () {
    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
        '*/athlete/activities*' => Http::response([['id' => 1]]),
    ]);

    $strava = app(Strava::class);

    expect($strava->token())->toBe('fresh-token');
    expect(Cache::get('strava_access_token'))->toBe('fresh-token');

    $strava->activitiesPage(1, 200);
    $strava->activitiesPage(2, 200);

    // One token exchange, reused for both subsequent page requests.
    Http::assertSentCount(3);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/athlete/activities')
        && $request->hasHeader('Authorization', 'Bearer fresh-token'));
});

it('re-authenticates and retries once on a 401', function () {
    Http::fake([
        '*/oauth/token*' => Http::sequence()
            ->push(['access_token' => 'expired', 'expires_in' => 3600])
            ->push(['access_token' => 'renewed', 'expires_in' => 3600]),
        '*/activities/55*' => Http::sequence()
            ->push(['error' => 'unauthorized'], 401)
            ->push(['id' => 55, 'name' => 'Ride']),
    ]);

    expect(app(Strava::class)->activity(55))->toBe(['id' => 55, 'name' => 'Ride']);
});

it('returns null when the token cannot be refreshed', function () {
    Http::fake([
        '*/oauth/token*' => Http::response('nope', 401),
    ]);

    expect(app(Strava::class)->token())->toBeNull();
    expect(app(Strava::class)->activitiesPage(1, 200))->toBeNull();
});

it('returns the photos payload for an activity', function () {
    Cache::put('strava_access_token', 'cached', 3600);

    Http::fake([
        '*/activities/9/photos*' => Http::response([['urls' => ['2048' => 'https://example/p.jpg']]]),
    ]);

    expect(app(Strava::class)->activityPhotos(9))->toBe([['urls' => ['2048' => 'https://example/p.jpg']]]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'size=2048'));
});
