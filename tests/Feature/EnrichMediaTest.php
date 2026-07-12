<?php

use App\Jobs\EnrichMedia;
use App\Models\Media;
use App\Models\Series;
use App\Services\Omdb;
use App\Services\Tmdb;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('services.tmdb.key', 'test-tmdb-key');
    config()->set('services.tmdb.image_base', 'https://image.tmdb.org/t/p/');
    config()->set('services.omdb.key', 'test-omdb-key');
    Storage::fake(config('media-library.disk_name'));
});

/**
 * A single fake covering every endpoint EnrichMedia can call: TMDB tv/movie
 * detail, TMDB images, the TMDB image CDN (returns the shared pixel fixture),
 * and OMDB. Order matters: `/images` must be checked before the bare detail
 * path since it's a substring match.
 */
function fakeEnrichmentApis(): void
{
    Http::fake(function ($request) {
        $url = $request->url();

        return match (true) {
            str_contains($url, '/tv/71712/images') => Http::response([
                'logos' => [
                    ['file_path' => '/logo-fr.png', 'iso_639_1' => 'fr'],
                    ['file_path' => '/logo-en.png', 'iso_639_1' => 'en'],
                ],
            ], 200),
            str_contains($url, '/tv/71712') => Http::response([
                'id' => 71712,
                'status' => 'Ended',
                'genres' => [['id' => 35, 'name' => 'Comedy'], ['id' => 14, 'name' => 'Fantasy']],
                'tagline' => 'The end is nigh',
                'vote_average' => 8.1,
                'poster_path' => '/poster.jpg',
                'backdrop_path' => '/backdrop.jpg',
                'number_of_seasons' => 2,
                'seasons' => [
                    ['season_number' => 1, 'name' => 'Season 1', 'episode_count' => 6, 'air_date' => '2019-05-31'],
                    ['season_number' => 2, 'name' => 'Season 2', 'episode_count' => 6, 'air_date' => '2023-07-28'],
                ],
            ], 200),
            str_contains($url, '/movie/438631') => Http::response([
                'id' => 438631,
                'status' => 'Released',
                'genres' => [['id' => 878, 'name' => 'Science Fiction']],
                'tagline' => 'Beyond fear, destiny awaits.',
                'vote_average' => 8.0,
                'poster_path' => null,
                'backdrop_path' => null,
            ], 200),
            str_contains($url, 'image.tmdb.org') => Http::response(file_get_contents(base_path('tests/Fixtures/pixel.webp')), 200),
            str_contains($url, 'trakt.tv') => Http::response(file_get_contents(base_path('tests/Fixtures/pixel.webp')), 200),
            str_contains($url, 'omdbapi.com') => Http::response([
                'Response' => 'True',
                'Rated' => 'TV-MA',
                'Awards' => 'Nominated for 1 Primetime Emmy.',
                'imdbRating' => '8.1',
                'imdbVotes' => '150,000',
                'Ratings' => [
                    ['Source' => 'Internet Movie Database', 'Value' => '8.1/10'],
                    ['Source' => 'Rotten Tomatoes', 'Value' => '85%'],
                    ['Source' => 'Metacritic', 'Value' => '74/100'],
                ],
            ], 200),
            default => Http::response([], 404),
        };
    });
}

it('enriches a series with tmdb structure, ratings, and downloaded art', function () {
    fakeEnrichmentApis();

    $series = Series::factory()->create();
    (new EnrichMedia($series, 'tv', 71712, 'tt6470478', null))->handle(app(Tmdb::class), app(Omdb::class));

    $fresh = $series->fresh();

    expect($fresh->meta['seasons'])->toBe(2)
        ->and($fresh->meta['season_list'])->toHaveCount(2)
        ->and($fresh->meta['season_list'][0])->toMatchArray([
            'number' => 1,
            'name' => 'Season 1',
            'episode_count' => 6,
            'air_date' => '2019-05-31',
        ])
        ->and($fresh->meta['tmdb'])->toMatchArray([
            'id' => 71712,
            'status' => 'Ended',
            'genres' => ['Comedy', 'Fantasy'],
            'tagline' => 'The end is nigh',
            'vote' => 8.1,
        ])
        ->and($fresh->meta['ratings']['rotten_tomatoes'])->toBe('85%')
        ->and($fresh->meta['ratings']['metacritic'])->toBe('74/100')
        ->and($fresh->meta['ratings']['certification'])->toBe('TV-MA')
        ->and($fresh->getFirstMedia('cover'))->not->toBeNull()
        ->and($fresh->getFirstMedia('backdrop'))->not->toBeNull()
        ->and($fresh->getFirstMedia('logo'))->not->toBeNull();
});

it('falls back to the trakt poster when tmdb has no poster', function () {
    fakeEnrichmentApis();

    $media = Media::factory()->create(['type' => 'film']);
    (new EnrichMedia($media, 'movie', 438631, null, 'walter-r2.trakt.tv/posters/dune-2021.jpg'))
        ->handle(app(Tmdb::class), app(Omdb::class));

    expect($media->fresh()->getFirstMedia('cover'))->not->toBeNull();
});

it('skips ratings gracefully when omdb reports no match', function () {
    Http::fake(function ($request) {
        $url = $request->url();

        return match (true) {
            str_contains($url, 'omdbapi.com') => Http::response(['Response' => 'False', 'Error' => 'Incorrect IMDb ID.'], 200),
            default => Http::response([], 404),
        };
    });

    $series = Series::factory()->create();

    (new EnrichMedia($series, 'tv', null, 'tt0000000', null))
        ->handle(app(Tmdb::class), app(Omdb::class));

    expect($series->fresh()->meta['ratings'] ?? null)->toBeNull();
});
