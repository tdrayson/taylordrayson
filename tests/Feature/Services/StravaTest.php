<?php

use App\Services\Strava;
use App\Services\Strava\ActivityRequest;
use App\Services\Strava\TokenRequest;
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

/**
 * Hand back the given responses in order for one request class.
 *
 * Saloon keys a mock by request class or URL and takes a single response per
 * key, so a per-key sequence has to be a closure over its own cursor.
 *
 * @param  list<MockResponse>  $responses
 */
function inOrder(array $responses): Closure
{
    $sent = 0;

    return function () use ($responses, &$sent): MockResponse {
        return $responses[$sent++] ?? end($responses);
    };
}

it('refreshes and caches the access token, reusing it across calls', function () {
    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'fresh-token', 'expires_in' => 3600]),
        '/athlete/activities*' => MockResponse::make([['id' => 1]]),
    ]);

    $strava = app(Strava::class);

    expect($strava->token())->toBe('fresh-token');
    expect(Cache::get('strava_access_token'))->toBe('fresh-token');

    $strava->activitiesPage(1, 200);
    $strava->activitiesPage(2, 200);

    // One token exchange, reused for both subsequent page requests.
    Saloon::assertSentCount(3);
    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), '/athlete/activities')
        && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer fresh-token');
});

it('re-authenticates and retries once on a 401', function () {
    Saloon::fake([
        TokenRequest::class => inOrder([
            MockResponse::make(['access_token' => 'expired', 'expires_in' => 3600]),
            MockResponse::make(['access_token' => 'renewed', 'expires_in' => 3600]),
        ]),
        ActivityRequest::class => inOrder([
            MockResponse::make(['error' => 'unauthorized'], 401),
            MockResponse::make(['id' => 55, 'name' => 'Ride']),
        ]),
    ]);

    expect(app(Strava::class)->activity(55))->toBe(['id' => 55, 'name' => 'Ride']);

    // The dead token is replaced rather than left to fail the next call too.
    expect(Cache::get('strava_access_token'))->toBe('renewed');
});

it('returns null when the token cannot be refreshed', function () {
    Saloon::fake([
        '/oauth/token*' => MockResponse::make('nope', 401),
    ]);

    expect(app(Strava::class)->token())->toBeNull();
    expect(app(Strava::class)->activitiesPage(1, 200))->toBeNull();
});

it('returns the photos payload for an activity', function () {
    Cache::put('strava_access_token', 'cached', 3600);

    Saloon::fake([
        '/activities/9/photos*' => MockResponse::make([['urls' => ['2048' => 'https://example/p.jpg']]]),
    ]);

    expect(app(Strava::class)->activityPhotos(9))->toBe([['urls' => ['2048' => 'https://example/p.jpg']]]);

    Saloon::assertSent(fn ($request) => $request->query()->get('size') === 2048);
});
