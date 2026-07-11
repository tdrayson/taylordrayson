<?php

use App\Services\Trakt;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.trakt.client_id', 'test-client-id');
    config()->set('services.trakt.username', 'taylor');
});

it('requests a history page with the required headers and params', function () {
    Http::fake([
        'api.trakt.tv/*' => Http::response([
            ['id' => 1, 'watched_at' => '2024-01-01T20:00:00.000Z', 'action' => 'watch', 'type' => 'movie',
                'movie' => ['title' => 'Dune', 'year' => 2021, 'ids' => ['trakt' => 9, 'slug' => 'dune-2021', 'tmdb' => 438631]]],
        ], 200),
    ]);

    $result = app(Trakt::class)->historyPage('movies', 1, 100, '2024-01-01T00:00:00Z');

    expect($result)->toHaveCount(1)
        ->and($result[0]['movie']['title'])->toBe('Dune');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.trakt.tv/users/taylor/history/movies')
            && $request['extended'] === 'full'
            && $request['page'] == 1
            && $request['start_at'] === '2024-01-01T00:00:00Z'
            && $request->header('trakt-api-version')[0] === '2'
            && $request->header('trakt-api-key')[0] === 'test-client-id';
    });
});

it('returns null when the request fails', function () {
    Http::fake(['api.trakt.tv/*' => Http::response('nope', 500)]);

    expect(app(Trakt::class)->historyPage('episodes', 1))->toBeNull();
});
