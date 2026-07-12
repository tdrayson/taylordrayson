<?php

use App\Services\Omdb;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.omdb.key', 'test-api-key');
});

it('requests by imdb id with the api key and returns the decoded body', function () {
    Http::fake([
        'omdbapi.com/*' => Http::response([
            'Title' => 'Good Omens',
            'Rated' => 'TV-MA',
            'Awards' => 'Nominated for 1 Primetime Emmy.',
            'imdbRating' => '8.1',
            'Ratings' => [
                ['Source' => 'Internet Movie Database', 'Value' => '8.1/10'],
                ['Source' => 'Rotten Tomatoes', 'Value' => '84%'],
            ],
            'Response' => 'True',
        ], 200),
    ]);

    $result = app(Omdb::class)->byImdb('tt6470478');

    expect($result)->toMatchArray([
        'Rated' => 'TV-MA',
        'Awards' => 'Nominated for 1 Primetime Emmy.',
        'imdbRating' => '8.1',
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'omdbapi.com')
            && $request['apikey'] === 'test-api-key'
            && $request['i'] === 'tt6470478';
    });
});

it('returns null when omdb reports Response false', function () {
    Http::fake([
        'omdbapi.com/*' => Http::response([
            'Response' => 'False',
            'Error' => 'Incorrect IMDb ID.',
        ], 200),
    ]);

    expect(app(Omdb::class)->byImdb('tt0000000'))->toBeNull();
});

it('returns null when the request fails', function () {
    Http::fake(['omdbapi.com/*' => Http::response('nope', 500)]);

    expect(app(Omdb::class)->byImdb('tt6470478'))->toBeNull();
});
