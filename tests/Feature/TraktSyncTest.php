<?php

use App\Jobs\EnrichMedia;
use App\Models\Media;
use App\Models\Series;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.trakt.client_id', 'k');
    config()->set('services.trakt.username', 'taylor');
    Bus::fake();
});

/**
 * The film history item carries its poster inline (as `extended=full`
 * really returns); the show summary response carries its poster too, so
 * both the embedded and the fallback poster-resolution paths get exercised.
 */
function fakeTraktHistory(array $movies, array $episodes): void
{
    Http::fake(function ($request) use ($movies, $episodes) {
        return match (true) {
            str_contains($request->url(), '/history/movies') => Http::response($request['page'] == 1 ? $movies : [], 200),
            str_contains($request->url(), '/history/episodes') => Http::response($request['page'] == 1 ? $episodes : [], 200),
            str_contains($request->url(), '/shows/') => Http::response([
                'aired_episodes' => 20,
                'ids' => ['slug' => 'severance'],
                'title' => 'Severance',
                'images' => ['poster' => ['walter-r2.trakt.tv/posters/severance.jpg']],
            ], 200),
            default => Http::response([], 200),
        };
    });
}

it('imports films and episodes, groups same-name shows by distinct trakt id, and dedupes on re-run', function () {
    fakeTraktHistory(
        movies: [[
            'id' => 501, 'watched_at' => '2024-01-01T20:00:00.000Z', 'action' => 'watch', 'type' => 'movie',
            'movie' => [
                'title' => 'Dune', 'year' => 2021, 'runtime' => 155,
                'ids' => ['trakt' => 9, 'slug' => 'dune-2021', 'tmdb' => 438631],
                'images' => ['poster' => ['walter-r2.trakt.tv/posters/dune-2021.jpg']],
            ],
        ]],
        episodes: [
            ['id' => 601, 'watched_at' => '2024-02-01T20:00:00.000Z', 'action' => 'watch', 'type' => 'episode',
                'episode' => ['season' => 1, 'number' => 1, 'title' => 'Good News', 'runtime' => 50, 'ids' => ['trakt' => 111]],
                'show' => ['title' => 'The Office', 'year' => 2005, 'ids' => ['trakt' => 700, 'slug' => 'the-office-us', 'tmdb' => 2316]]],
            ['id' => 602, 'watched_at' => '2024-02-01T21:00:00.000Z', 'action' => 'watch', 'type' => 'episode',
                'episode' => ['season' => 1, 'number' => 1, 'title' => 'Downsize', 'runtime' => 30, 'ids' => ['trakt' => 222]],
                'show' => ['title' => 'The Office', 'year' => 2001, 'ids' => ['trakt' => 800, 'slug' => 'the-office', 'tmdb' => 2996]]],
        ],
    );

    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    // Episodes are processed in array order, so the US show (2005, trakt id
    // 700) is resolved first and claims the bare "the-office" slug; the UK
    // show (2001, trakt id 800) is resolved second and finds it taken, so it
    // gets year-disambiguated to "the-office-2001". Neither slug carries a
    // Trakt id, which is the whole point: URLs stay service-independent.
    expect(Media::where('type', 'film')->count())->toBe(1)
        ->and(Media::where('type', 'episode')->count())->toBe(2)
        ->and(Series::count())->toBe(2) // two distinct shows despite identical title
        ->and(Series::pluck('slug')->sort()->values()->all())->toEqual(['the-office', 'the-office-2001']);

    // Re-run creates nothing new.
    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();
    expect(Media::count())->toBe(3);

    // One poster per new subject: the film (poster embedded in the history
    // item) and both new shows (poster resolved via the /shows/{id} summary
    // fallback, since the inline `show` payload carries no `images`).
    Bus::assertDispatched(EnrichMedia::class, 3);
});
