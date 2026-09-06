<?php

use App\Services\PocketCasts\Client;
use Illuminate\Support\Facades\Cache;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config()->set('services.pocketcasts.email', 'me@example.com');
    config()->set('services.pocketcasts.password', 'secret');
    Cache::flush();
});

it('logs in then calls an endpoint with the bearer token', function () {
    Saloon::fake([
        'api.pocketcasts.com/user/login' => MockResponse::make(['token' => 'jwt-123', 'uuid' => 'u-1']),
        'api.pocketcasts.com/user/podcast/list' => MockResponse::make(['podcasts' => [['uuid' => 'p1']]]),
    ]);

    expect(app(Client::class)->subscriptions())->toBe(['podcasts' => [['uuid' => 'p1']]]);

    Saloon::assertSent(fn ($request, $response) => $response->getPendingRequest()->getUrl() === 'https://api.pocketcasts.com/user/login'
        && $request->body()->all()['email'] === 'me@example.com'
        && $request->body()->all()['scope'] === 'webplayer');

    Saloon::assertSent(fn ($request, $response) => $response->getPendingRequest()->getUrl() === 'https://api.pocketcasts.com/user/podcast/list'
        && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer jwt-123'
        // PostRequest encodes its own JSON so an empty body stays `{}`.
        && json_decode($request->body()->all(), true)['v'] === 1);
});

it('caches the token and logs in only once across calls', function () {
    Saloon::fake([
        'api.pocketcasts.com/user/login' => MockResponse::make(['token' => 'jwt-123']),
        'api.pocketcasts.com/*' => MockResponse::make(['ok' => true]),
    ]);

    $client = app(Client::class);
    $client->history();
    $client->starred();

    Saloon::assertSentCount(3);
});

it('re-authenticates and retries once on a 401', function () {
    Saloon::fake([
        'api.pocketcasts.com/user/login' => mockSequence([

            MockResponse::make(['token' => 'expired']),

            MockResponse::make(['token' => 'fresh']),

        ]),
        'api.pocketcasts.com/user/history' => mockSequence([

            MockResponse::make(['error' => 'unauthorized'], 401),

            MockResponse::make(['history' => []]),

        ]),
    ]);

    expect(app(Client::class)->history())->toBe(['history' => []]);

    Saloon::assertSentCount(4);
});

it('sends the search term', function () {
    Saloon::fake([
        'api.pocketcasts.com/user/login' => MockResponse::make(['token' => 'jwt']),
        'api.pocketcasts.com/discover/search' => MockResponse::make(['podcasts' => []]),
    ]);

    app(Client::class)->search('syntax');

    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), '/discover/search') && json_decode($request->body()->all(), true)['term'] === 'syntax');
});

it('reads episode show notes from the podcast-api host', function () {
    Saloon::fake([
        'api.pocketcasts.com/user/login' => MockResponse::make(['token' => 'jwt']),
        'podcast-api.pocketcasts.com/episode/show_notes/*' => MockResponse::make(['show_notes' => 'Notes']),
    ]);

    expect(app(Client::class)->showNotes('ep-1'))->toBe(['show_notes' => 'Notes']);

    Saloon::assertSent(fn ($request, $response) => $response->getPendingRequest()->getUrl() === 'https://podcast-api.pocketcasts.com/episode/show_notes/ep-1'
        && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer jwt');
});

it('reads a public discover feed without authenticating', function () {
    Saloon::fake([
        'static.pocketcasts.com/discover/json/popular_world.json' => MockResponse::make(['status' => 'ok', 'result' => []]),
    ]);

    expect(app(Client::class)->popular())->toBe(['status' => 'ok', 'result' => []]);

    Saloon::assertNotSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), '/user/login'));
    Saloon::assertSent(fn ($request, $response) => $response->getPendingRequest()->getUrl() === 'https://static.pocketcasts.com/discover/json/popular_world.json'
        && $response->getPendingRequest()->headers()->get('Authorization') === null);
});

it('throws when credentials are not configured', function () {
    config()->set('services.pocketcasts.email', null);

    app(Client::class)->history();
})->throws(RuntimeException::class);
