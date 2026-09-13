<?php

use App\Services\Tmdb\Client;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config()->set('services.tmdb.key', 'test-api-key');
    config()->set('services.tmdb.image_base', 'https://image.tmdb.org/t/p/');
});

it('requests tv details with the api key and returns the decoded body', function () {
    Saloon::fake([
        'api.themoviedb.org/*' => MockResponse::make([
            'id' => 71712,
            'name' => 'Good Omens',
            'number_of_seasons' => 2,
        ], 200),
    ]);

    $result = app(Client::class)->tv(71712);

    expect($result)->toMatchArray([
        'id' => 71712,
        'name' => 'Good Omens',
        'number_of_seasons' => 2,
    ]);

    Saloon::assertSent(function ($request, $response) {
        return str_contains($response->getPendingRequest()->getUrl(), 'api.themoviedb.org/3/tv/71712')
            && $response->getPendingRequest()->query()->get('api_key') === 'test-api-key';
    });
});

it('requests movie details with the api key and returns the decoded body', function () {
    Saloon::fake([
        'api.themoviedb.org/*' => MockResponse::make(['id' => 438631, 'title' => 'Dune'], 200),
    ]);

    $result = app(Client::class)->movie(438631);

    expect($result)->toMatchArray(['id' => 438631, 'title' => 'Dune']);

    Saloon::assertSent(function ($request, $response) {
        return str_contains($response->getPendingRequest()->getUrl(), 'api.themoviedb.org/3/movie/438631')
            && $response->getPendingRequest()->query()->get('api_key') === 'test-api-key';
    });
});

it('requests images for a given kind and id', function () {
    Saloon::fake([
        'api.themoviedb.org/*' => MockResponse::make(['logos' => [['file_path' => '/logo.png']]], 200),
    ]);

    $result = app(Client::class)->images('tv', 71712);

    expect($result)->toMatchArray(['logos' => [['file_path' => '/logo.png']]]);

    Saloon::assertSent(function ($request, $response) {
        return str_contains($response->getPendingRequest()->getUrl(), 'api.themoviedb.org/3/tv/71712/images')
            && $response->getPendingRequest()->query()->get('api_key') === 'test-api-key';
    });
});

it('builds an image url from a path and size', function () {
    expect(app(Client::class)->imageUrl('/abc.jpg', 'w780'))
        ->toBe('https://image.tmdb.org/t/p/w780/abc.jpg');
});

it('returns null for an image url when the path is null', function () {
    expect(app(Client::class)->imageUrl(null, 'w780'))->toBeNull();
});

it('returns null when the request fails', function () {
    Saloon::fake(['api.themoviedb.org/*' => MockResponse::make('nope', 500)]);

    expect(app(Client::class)->tv(71712))->toBeNull();
});
