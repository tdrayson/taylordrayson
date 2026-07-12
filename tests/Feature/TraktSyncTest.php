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
 *
 * `$ratings` optionally carries `{movies, episodes, shows}` arrays of Trakt
 * ratings-endpoint items; omitted keys fake as "nothing rated" (empty page),
 * so tests that don't care about ratings see `syncRatings()` no-op cleanly.
 */
function fakeTraktHistory(array $movies, array $episodes, array $ratings = []): void
{
    $movieRatings = $ratings['movies'] ?? [];
    $episodeRatings = $ratings['episodes'] ?? [];
    $showRatings = $ratings['shows'] ?? [];

    Http::fake(function ($request) use ($movies, $episodes, $movieRatings, $episodeRatings, $showRatings) {
        return match (true) {
            str_contains($request->url(), '/history/movies') => Http::response($request['page'] == 1 ? $movies : [], 200),
            str_contains($request->url(), '/history/episodes') => Http::response($request['page'] == 1 ? $episodes : [], 200),
            str_contains($request->url(), '/ratings/movies') => Http::response($request['page'] == 1 ? $movieRatings : [], 200),
            str_contains($request->url(), '/ratings/episodes') => Http::response($request['page'] == 1 ? $episodeRatings : [], 200),
            str_contains($request->url(), '/ratings/shows') => Http::response($request['page'] == 1 ? $showRatings : [], 200),
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

it('imports personal star ratings onto films, episodes, and series, staying idempotent on re-run', function () {
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
        ],
        ratings: [
            'movies' => [
                ['rating' => 9, 'rated_at' => '2024-01-02T00:00:00.000Z', 'movie' => ['ids' => ['trakt' => 9]]],
            ],
            'episodes' => [
                ['rating' => 8, 'rated_at' => '2024-02-02T00:00:00.000Z', 'episode' => ['ids' => ['trakt' => 111]]],
            ],
            'shows' => [
                ['rating' => 10, 'rated_at' => '2024-02-02T00:00:00.000Z', 'show' => ['ids' => ['trakt' => 700]]],
            ],
        ],
    );

    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    $film = Media::where('type', 'film')->firstOrFail();
    $episode = Media::where('type', 'episode')->firstOrFail();
    $series = Series::where('trakt_id', 700)->firstOrFail();

    expect($film->rating)->toBe(9)
        ->and($episode->rating)->toBe(8)
        ->and($series->meta['rating'])->toBe(10);

    // Re-run stays idempotent: same ratings applied again, nothing errors,
    // nothing changes.
    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    expect($film->fresh()->rating)->toBe(9)
        ->and($episode->fresh()->rating)->toBe(8)
        ->and($series->fresh()->meta['rating'])->toBe(10);
});

it('nudges episodes that share an exact watched_at into season/episode order, idempotently, and leaves distinct timestamps alone', function () {
    // No new history this run; only the normalization pass over already-imported rows matters here.
    fakeTraktHistory(movies: [], episodes: []);

    $series = Series::factory()->create();

    // Real Trakt data: bulk-marking watched gives S1E6 and S1E7 the exact
    // same second, seeded here already out of order (E7 row created first).
    $e7 = Media::create([
        'occurred_at' => '2026-01-02 07:32:00',
        'type' => 'episode',
        'title' => 'Seven',
        'series_id' => $series->id,
        'source' => 'trakt',
        'source_id' => 'e7',
        'meta' => ['season' => 1, 'episode' => 7],
    ]);
    $e6 = Media::create([
        'occurred_at' => '2026-01-02 07:32:00',
        'type' => 'episode',
        'title' => 'Six',
        'series_id' => $series->id,
        'source' => 'trakt',
        'source_id' => 'e6',
        'meta' => ['season' => 1, 'episode' => 6],
    ]);
    $e1 = Media::create([
        'occurred_at' => '2026-01-01 20:00:00',
        'type' => 'episode',
        'title' => 'One',
        'series_id' => $series->id,
        'source' => 'trakt',
        'source_id' => 'e1',
        'meta' => ['season' => 1, 'episode' => 1],
    ]);

    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    $e6 = $e6->fresh();
    $e7 = $e7->fresh();
    $e1 = $e1->fresh();

    // The lower (season, episode) keeps the original shared timestamp; the
    // higher one is pushed one second later, so a plain time-sort is correct.
    expect($e6->occurred_at->toDateTimeString())->toBe('2026-01-02 07:32:00')
        ->and($e7->occurred_at->toDateTimeString())->toBe('2026-01-02 07:32:01')
        ->and($e6->occurred_at->isBefore($e7->occurred_at))->toBeTrue()
        ->and($e6->occurred_at->diffInSeconds($e7->occurred_at))->toBe(1.0)
        ->and($e1->occurred_at->toDateTimeString())->toBe('2026-01-01 20:00:00');

    // Second run must be a no-op: the group is no longer tied, so nothing changes.
    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    expect($e6->fresh()->occurred_at->toDateTimeString())->toBe('2026-01-02 07:32:00')
        ->and($e7->fresh()->occurred_at->toDateTimeString())->toBe('2026-01-02 07:32:01')
        ->and($e1->fresh()->occurred_at->toDateTimeString())->toBe('2026-01-01 20:00:00');
});
