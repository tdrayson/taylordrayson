<?php

use App\Support\PocketCasts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.pocketcasts.email', 'me@example.com');
    config()->set('services.pocketcasts.password', 'secret');
    Cache::flush();
});

it('logs in then calls an endpoint with the bearer token', function () {
    Http::fake([
        'api.pocketcasts.com/user/login' => Http::response(['token' => 'jwt-123', 'uuid' => 'u-1']),
        'api.pocketcasts.com/user/podcast/list' => Http::response(['podcasts' => [['uuid' => 'p1']]]),
    ]);

    expect(app(PocketCasts::class)->subscriptions())->toBe(['podcasts' => [['uuid' => 'p1']]]);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.pocketcasts.com/user/login'
        && $request['email'] === 'me@example.com'
        && $request['scope'] === 'webplayer');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.pocketcasts.com/user/podcast/list'
        && $request->hasHeader('Authorization', 'Bearer jwt-123')
        && $request['v'] === 1);
});

it('caches the token and logs in only once across calls', function () {
    Http::fake([
        'api.pocketcasts.com/user/login' => Http::response(['token' => 'jwt-123']),
        'api.pocketcasts.com/*' => Http::response(['ok' => true]),
    ]);

    $client = app(PocketCasts::class);
    $client->history();
    $client->starred();

    Http::assertSentCount(3);
});

it('re-authenticates and retries once on a 401', function () {
    Http::fake([
        'api.pocketcasts.com/user/login' => Http::sequence()
            ->push(['token' => 'expired'])
            ->push(['token' => 'fresh']),
        'api.pocketcasts.com/user/history' => Http::sequence()
            ->push(['error' => 'unauthorized'], 401)
            ->push(['history' => []]),
    ]);

    expect(app(PocketCasts::class)->history())->toBe(['history' => []]);

    Http::assertSentCount(4);
});

it('sends the search term', function () {
    Http::fake([
        'api.pocketcasts.com/user/login' => Http::response(['token' => 'jwt']),
        'api.pocketcasts.com/discover/search' => Http::response(['podcasts' => []]),
    ]);

    app(PocketCasts::class)->search('syntax');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/discover/search') && $request['term'] === 'syntax');
});

it('reads episode show notes from the podcast-api host', function () {
    Http::fake([
        'api.pocketcasts.com/user/login' => Http::response(['token' => 'jwt']),
        'podcast-api.pocketcasts.com/episode/show_notes/*' => Http::response(['show_notes' => 'Notes']),
    ]);

    expect(app(PocketCasts::class)->showNotes('ep-1'))->toBe(['show_notes' => 'Notes']);

    Http::assertSent(fn ($request) => $request->url() === 'https://podcast-api.pocketcasts.com/episode/show_notes/ep-1'
        && $request->hasHeader('Authorization', 'Bearer jwt'));
});

it('reads a public discover feed without authenticating', function () {
    Http::fake([
        'static.pocketcasts.com/discover/json/popular_world.json' => Http::response(['status' => 'ok', 'result' => []]),
    ]);

    expect(app(PocketCasts::class)->popular())->toBe(['status' => 'ok', 'result' => []]);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/user/login'));
    Http::assertSent(fn ($request) => $request->url() === 'https://static.pocketcasts.com/discover/json/popular_world.json'
        && ! $request->hasHeader('Authorization'));
});

it('throws when credentials are not configured', function () {
    config()->set('services.pocketcasts.email', null);

    app(PocketCasts::class)->history();
})->throws(RuntimeException::class);
