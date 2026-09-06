<?php

use App\Jobs\EnrichMedia;
use App\Models\Media;
use App\Models\Series;
use App\Services\Tmdb\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

// Poster and still images are fetched with Http::get(), not through a
// connector, so they need Laravel's own fake alongside Saloon's.
beforeEach(fn () => Http::fake(['*' => Http::response(fakeJpeg())]));

beforeEach(function () {
    config()->set('services.tmdb.key', 'test-tmdb-key');
    config()->set('services.tmdb.image_base', 'https://image.tmdb.org/t/p/');
    Storage::fake(config('media-library.disk_name'));
});

/**
 * A single fake covering every endpoint EnrichMedia can call: TMDB tv/movie
 * detail, TMDB images, and the TMDB image CDN (returns the shared pixel
 * fixture). Order matters: `/images` must be checked before the bare detail
 * path since it's a substring match.
 */
function fakeEnrichmentApis(): void
{
    Saloon::fake(['' => function ($pendingRequest) {
        $url = $pendingRequest->getUrl();

        return match (true) {
            str_contains($url, '/tv/71712/images') => MockResponse::make([
                'logos' => [
                    ['file_path' => '/logo-fr.png', 'iso_639_1' => 'fr'],
                    ['file_path' => '/logo-en.png', 'iso_639_1' => 'en'],
                ],
            ], 200),
            str_contains($url, '/tv/71712') => MockResponse::make([
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
            str_contains($url, '/movie/438631') => MockResponse::make([
                'id' => 438631,
                'status' => 'Released',
                'genres' => [['id' => 878, 'name' => 'Science Fiction']],
                'tagline' => 'Beyond fear, destiny awaits.',
                'vote_average' => 8.0,
                'poster_path' => null,
                'backdrop_path' => null,
            ], 200),
            str_contains($url, 'image.tmdb.org') => MockResponse::make(file_get_contents(base_path('tests/Fixtures/pixel.webp')), 200),
            str_contains($url, 'trakt.tv') => MockResponse::make(file_get_contents(base_path('tests/Fixtures/pixel.webp')), 200),
            default => MockResponse::make([], 404),
        };
    }]);
}

it('enriches a series with tmdb structure and downloaded art', function () {
    fakeEnrichmentApis();

    $series = Series::factory()->create();
    (new EnrichMedia($series, 'tv', 71712, null))->handle(app(Client::class));

    $fresh = $series->fresh();

    expect($fresh->meta->seasons)->toBe(2)
        ->and($fresh->meta->seasonList)->toHaveCount(2)
        ->and($fresh->meta->seasonList[0]->toArray())->toMatchArray([
            'number' => 1,
            'name' => 'Season 1',
            'episodeCount' => 6,
            'airDate' => '2019-05-31',
        ])
        ->and($fresh->meta->tmdb->toArray())->toMatchArray([
            'id' => 71712,
            'status' => 'Ended',
            'genres' => ['Comedy', 'Fantasy'],
            'tagline' => 'The end is nigh',
            'vote' => 8.1,
        ])
        ->and($fresh->getFirstMedia('cover'))->not->toBeNull()
        ->and($fresh->getFirstMedia('backdrop'))->not->toBeNull()
        ->and($fresh->getFirstMedia('logo'))->not->toBeNull();
});

it('falls back to the trakt poster when tmdb has no poster', function () {
    fakeEnrichmentApis();

    $media = Media::factory()->create(['type' => 'film']);
    (new EnrichMedia($media, 'movie', 438631, 'walter-r2.trakt.tv/posters/dune-2021.jpg'))
        ->handle(app(Client::class));

    expect($media->fresh()->getFirstMedia('cover'))->not->toBeNull();
});
