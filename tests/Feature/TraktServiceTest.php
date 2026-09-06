<?php

use App\Exceptions\TraktException;
use App\Services\Trakt\Client;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config()->set('services.trakt.client_id', 'test-client-id');
    config()->set('services.trakt.username', 'taylor');
});

it('requests a history page with the required headers and params', function () {
    Saloon::fake([
        'api.trakt.tv/*' => MockResponse::make([
            ['id' => 1, 'watched_at' => '2024-01-01T20:00:00.000Z', 'action' => 'watch', 'type' => 'movie',
                'movie' => ['title' => 'Dune', 'year' => 2021, 'ids' => ['trakt' => 9, 'slug' => 'dune-2021', 'tmdb' => 438631]]],
        ], 200),
    ]);

    $result = app(Client::class)->historyPage('movies', 1, 100, '2024-01-01T00:00:00Z');

    expect($result)->toHaveCount(1)
        ->and($result[0]['movie']['title'])->toBe('Dune');

    Saloon::assertSent(function ($request, $response) {
        return str_contains($response->getPendingRequest()->getUrl(), 'api.trakt.tv/users/taylor/history/movies')
            && $request->query()->get('extended') === 'full'
            && $request->query()->get('page') == 1
            && $request->query()->get('start_at') === '2024-01-01T00:00:00Z'
            && $response->getPendingRequest()->headers()->get('trakt-api-version') === '2'
            && $response->getPendingRequest()->headers()->get('trakt-api-key') === 'test-client-id';
    });
});

it('throws a TraktException when a history page request fails', function () {
    Saloon::fake(['api.trakt.tv/*' => MockResponse::make('nope', 500)]);

    expect(fn () => app(Client::class)->historyPage('episodes', 1))
        ->toThrow(TraktException::class);
});

it('throws a TraktException when a ratings page request fails', function () {
    Saloon::fake(['api.trakt.tv/*' => MockResponse::make('nope', 500)]);

    expect(fn () => app(Client::class)->ratingsPage('movies', 1))
        ->toThrow(TraktException::class);
});

it('retries a 429 response and resolves to the eventual 200 body', function () {
    Saloon::fake([
        'api.trakt.tv/*' => mockSequence([

            MockResponse::make('rate limited', 429),

            MockResponse::make([
                ['id' => 1, 'watched_at' => '2024-01-01T20:00:00.000Z', 'action' => 'watch', 'type' => 'movie',
                    'movie' => ['title' => 'Dune', 'year' => 2021, 'ids' => ['trakt' => 9, 'slug' => 'dune-2021', 'tmdb' => 438631]]],
            ], 200),

        ]),
    ]);

    $result = app(Client::class)->historyPage('movies', 1);

    expect($result)->toHaveCount(1)
        ->and($result[0]['movie']['title'])->toBe('Dune');

    Saloon::assertSentCount(2);
});

it('remains null-tolerant for show and movie summary lookups', function () {
    Saloon::fake(['api.trakt.tv/*' => MockResponse::make('nope', 500)]);

    expect(app(Client::class)->show(700))->toBeNull()
        ->and(app(Client::class)->movie(9))->toBeNull();
});
