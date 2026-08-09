<?php

use App\Jobs\EnrichMedia;
use App\Models\Media;
use App\Models\Series;
use Carbon\Carbon;
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

it('fails closed and stops importing when a history page request fails mid-pagination', function () {
    Http::fake(function ($request) {
        return match (true) {
            str_contains($request->url(), '/history/movies') => Http::response($request['page'] == 1 ? [[
                'id' => 501, 'watched_at' => '2024-01-01T20:00:00.000Z', 'action' => 'watch', 'type' => 'movie',
                'movie' => [
                    'title' => 'Dune', 'year' => 2021, 'runtime' => 155,
                    'ids' => ['trakt' => 9, 'slug' => 'dune-2021', 'tmdb' => 438631],
                    'images' => ['poster' => ['walter-r2.trakt.tv/posters/dune-2021.jpg']],
                ],
            ]] : [], 200),
            str_contains($request->url(), '/history/episodes') => Http::response('server error', 500),
            default => Http::response([], 200),
        };
    });

    $this->artisan('trakt:sync', ['--full' => true])->assertFailed();

    // The movies page succeeded and was imported before the episodes page
    // failed; the command still reports failure rather than silently
    // truncating the episode history.
    expect(Media::where('type', 'film')->count())->toBe(1)
        ->and(Media::where('type', 'episode')->count())->toBe(0);
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
        ->and($series->meta->rating)->toBe(10);

    // Re-run stays idempotent: same ratings applied again, nothing errors,
    // nothing changes.
    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    expect($film->fresh()->rating)->toBe(9)
        ->and($episode->fresh()->rating)->toBe(8)
        ->and($series->fresh()->meta->rating)->toBe(10);
});

it('nudges episodes that share an exact watched_at into season/episode order, idempotently, and leaves distinct timestamps alone', function () {
    $series = Series::factory()->create(['trakt_id' => 700]);

    // Normalization is scoped to series synced this run (see the scoping
    // test below), so this run also imports one new episode of the same
    // series (a different season/episode, on a different date, so it
    // doesn't collide with the seeded E1/E6/E7 tie below) purely to mark
    // the series "affected".
    fakeTraktHistory(movies: [], episodes: [
        ['id' => 900, 'watched_at' => '2026-01-05T12:00:00.000Z', 'action' => 'watch', 'type' => 'episode',
            'episode' => ['season' => 1, 'number' => 10, 'title' => 'Ten', 'runtime' => 50, 'ids' => ['trakt' => 910]],
            'show' => ['title' => 'Show Title', 'year' => 2020, 'ids' => ['trakt' => 700, 'slug' => 'show-slug', 'tmdb' => 2316]]],
    ]);

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

it('clamps a tied group nudged near midnight so it stays inside the same local calendar day', function () {
    $series = Series::factory()->create(['trakt_id' => 701]);

    // Normalization is scoped to series synced this run, so this run also
    // imports one new episode of the same series (a different season/episode,
    // on a different date, so it doesn't collide with the seeded E1/E2/E3
    // tie below) purely to mark the series "affected".
    fakeTraktHistory(movies: [], episodes: [
        ['id' => 901, 'watched_at' => '2026-01-05T12:00:00.000Z', 'action' => 'watch', 'type' => 'episode',
            'episode' => ['season' => 1, 'number' => 10, 'title' => 'Ten', 'runtime' => 50, 'ids' => ['trakt' => 911]],
            'show' => ['title' => 'Show Title', 'year' => 2020, 'ids' => ['trakt' => 701, 'slug' => 'show-slug-2', 'tmdb' => 2317]]],
    ]);

    // All three share the exact same second, one second before midnight, and
    // are seeded out of (season, episode) order so the sort itself is exercised
    // too. Naively assigning base+rank would push E3 to 2026-01-03 00:00:01.
    $e3 = Media::create([
        'occurred_at' => '2026-01-02 23:59:59',
        'timezone' => 'Europe/London',
        'type' => 'episode',
        'title' => 'Three',
        'series_id' => $series->id,
        'source' => 'trakt',
        'source_id' => 'e3',
        'meta' => ['season' => 1, 'episode' => 3],
    ]);
    $e1 = Media::create([
        'occurred_at' => '2026-01-02 23:59:59',
        'timezone' => 'Europe/London',
        'type' => 'episode',
        'title' => 'One',
        'series_id' => $series->id,
        'source' => 'trakt',
        'source_id' => 'e1',
        'meta' => ['season' => 1, 'episode' => 1],
    ]);
    $e2 = Media::create([
        'occurred_at' => '2026-01-02 23:59:59',
        'timezone' => 'Europe/London',
        'type' => 'episode',
        'title' => 'Two',
        'series_id' => $series->id,
        'source' => 'trakt',
        'source_id' => 'e2',
        'meta' => ['season' => 1, 'episode' => 2],
    ]);

    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    $e1 = $e1->fresh();
    $e2 = $e2->fresh();
    $e3 = $e3->fresh();

    // The group can't fit 3 one-second-apart slots before midnight starting
    // from 23:59:59, so the base shifts back to 23:59:57 instead of the last
    // episode rolling onto the next calendar day.
    expect($e1->occurred_at->toDateTimeString())->toBe('2026-01-02 23:59:57')
        ->and($e2->occurred_at->toDateTimeString())->toBe('2026-01-02 23:59:58')
        ->and($e3->occurred_at->toDateTimeString())->toBe('2026-01-02 23:59:59')
        ->and($e1->occurred_at->format('Y-m-d'))->toBe('2026-01-02')
        ->and($e2->occurred_at->format('Y-m-d'))->toBe('2026-01-02')
        ->and($e3->occurred_at->format('Y-m-d'))->toBe('2026-01-02')
        ->and($e1->occurred_at->isBefore($e2->occurred_at))->toBeTrue()
        ->and($e2->occurred_at->isBefore($e3->occurred_at))->toBeTrue();
});

it('scopes normalization to series synced this run, leaving another series tied timestamps unchanged', function () {
    $seriesA = Series::factory()->create(['trakt_id' => 702]);
    $seriesB = Series::factory()->create(['trakt_id' => 703]);

    // Series A already carries a tied group from a previous run; it receives
    // no new episode this run, so it must NOT be touched by normalize.
    $a1 = Media::create([
        'occurred_at' => '2026-01-02 07:32:00',
        'type' => 'episode',
        'title' => 'A One',
        'series_id' => $seriesA->id,
        'source' => 'trakt',
        'source_id' => 'a1',
        'meta' => ['season' => 1, 'episode' => 1],
    ]);
    $a2 = Media::create([
        'occurred_at' => '2026-01-02 07:32:00',
        'type' => 'episode',
        'title' => 'A Two',
        'series_id' => $seriesA->id,
        'source' => 'trakt',
        'source_id' => 'a2',
        'meta' => ['season' => 1, 'episode' => 2],
    ]);

    // Only series B receives a new episode this run.
    fakeTraktHistory(movies: [], episodes: [
        ['id' => 950, 'watched_at' => '2026-02-01T12:00:00.000Z', 'action' => 'watch', 'type' => 'episode',
            'episode' => ['season' => 1, 'number' => 1, 'title' => 'B One', 'runtime' => 50, 'ids' => ['trakt' => 951]],
            'show' => ['title' => 'Show B', 'year' => 2020, 'ids' => ['trakt' => 703, 'slug' => 'show-b', 'tmdb' => 3000]]],
    ]);

    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    // Series A wasn't affected this run, so its tied pair keeps sharing the
    // exact same timestamp instead of being nudged apart by a full-table scan.
    expect($a1->fresh()->occurred_at->toDateTimeString())->toBe('2026-01-02 07:32:00')
        ->and($a2->fresh()->occurred_at->toDateTimeString())->toBe('2026-01-02 07:32:00');
});

it('self-heals the sync window to the last synced watch when it is older than the default --days window', function () {
    fakeTraktHistory([], []);

    // Last synced watch is 30 days ago; default --days=7 would otherwise
    // miss the gap between day 7 and day 30 if a scheduled run was skipped.
    Media::create([
        'occurred_at' => now()->subDays(30)->format('Y-m-d H:i:s'),
        'timezone' => 'Europe/London',
        'type' => 'episode',
        'title' => 'Old Episode',
        'source' => 'trakt',
        'source_id' => 'old-1',
        'meta' => ['season' => 1, 'episode' => 1],
    ]);

    $this->artisan('trakt:sync')->assertSuccessful();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/history/movies')) {
            return false;
        }

        $daysAgo = Carbon::parse($request['start_at'])->diffInDays(now());

        return $daysAgo >= 29 && $daysAgo <= 31;
    });
});

it('caps the self-healed sync window at MAX_CATCHUP_DAYS when the last synced watch is much older', function () {
    fakeTraktHistory([], []);

    // Last synced watch is 200 days ago (e.g. sync was broken for months);
    // the window must be capped at MAX_CATCHUP_DAYS (90) rather than
    // requesting a huge, unbounded backfill.
    Media::create([
        'occurred_at' => now()->subDays(200)->format('Y-m-d H:i:s'),
        'timezone' => 'Europe/London',
        'type' => 'episode',
        'title' => 'Very Old Episode',
        'source' => 'trakt',
        'source_id' => 'old-2',
        'meta' => ['season' => 1, 'episode' => 1],
    ]);

    $this->artisan('trakt:sync')->assertSuccessful();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/history/movies')) {
            return false;
        }

        $daysAgo = Carbon::parse($request['start_at'])->diffInDays(now());

        return $daysAgo >= 89 && $daysAgo <= 91;
    });
});

it('re-dispatches enrichment for an existing bare series that receives a new episode this run', function () {
    // Bare: no cover media, and the factory's default meta carries no `tmdb`
    // key. One episode already exists so the series is genuinely pre-existing,
    // not created by this run.
    $series = Series::factory()->create(['trakt_id' => 700]);
    Media::create([
        'occurred_at' => '2024-01-01 20:00:00',
        'type' => 'episode',
        'title' => 'Existing',
        'series_id' => $series->id,
        'source' => 'trakt',
        'source_id' => 'existing-1',
        'meta' => ['season' => 1, 'episode' => 1],
    ]);

    fakeTraktHistory(movies: [], episodes: [
        ['id' => 601, 'watched_at' => '2024-02-01T20:00:00.000Z', 'action' => 'watch', 'type' => 'episode',
            'episode' => ['season' => 1, 'number' => 2, 'title' => 'New Ep', 'runtime' => 50, 'ids' => ['trakt' => 111]],
            'show' => ['title' => 'Severance', 'year' => 2022, 'ids' => ['trakt' => 700, 'slug' => 'severance', 'tmdb' => 2316]]],
    ]);

    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    // Not a new series, so the old `$wasNew`-only dispatch would have missed
    // this: the series is bare, so it must still re-enrich.
    Bus::assertDispatched(EnrichMedia::class, 1);
});

it('dispatches enrichment once for a bare series even when a batch carries several of its episodes', function () {
    $series = Series::factory()->create(['trakt_id' => 700]);
    Media::create([
        'occurred_at' => '2024-01-01 20:00:00',
        'type' => 'episode',
        'title' => 'Existing',
        'series_id' => $series->id,
        'source' => 'trakt',
        'source_id' => 'existing-1',
        'meta' => ['season' => 1, 'episode' => 1],
    ]);

    fakeTraktHistory(movies: [], episodes: [
        ['id' => 601, 'watched_at' => '2024-02-01T20:00:00.000Z', 'action' => 'watch', 'type' => 'episode',
            'episode' => ['season' => 1, 'number' => 2, 'title' => 'Ep 2', 'runtime' => 50, 'ids' => ['trakt' => 111]],
            'show' => ['title' => 'Severance', 'year' => 2022, 'ids' => ['trakt' => 700, 'slug' => 'severance', 'tmdb' => 2316]]],
        ['id' => 602, 'watched_at' => '2024-02-01T21:00:00.000Z', 'action' => 'watch', 'type' => 'episode',
            'episode' => ['season' => 1, 'number' => 3, 'title' => 'Ep 3', 'runtime' => 50, 'ids' => ['trakt' => 112]],
            'show' => ['title' => 'Severance', 'year' => 2022, 'ids' => ['trakt' => 700, 'slug' => 'severance', 'tmdb' => 2316]]],
        ['id' => 603, 'watched_at' => '2024-02-01T22:00:00.000Z', 'action' => 'watch', 'type' => 'episode',
            'episode' => ['season' => 1, 'number' => 4, 'title' => 'Ep 4', 'runtime' => 50, 'ids' => ['trakt' => 113]],
            'show' => ['title' => 'Severance', 'year' => 2022, 'ids' => ['trakt' => 700, 'slug' => 'severance', 'tmdb' => 2316]]],
    ]);

    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    expect(Media::where('type', 'episode')->count())->toBe(4); // 1 pre-existing + 3 new

    // Deduped per run: three new episodes of the same bare show still only
    // trigger one enrichment dispatch.
    Bus::assertDispatched(EnrichMedia::class, 1);
});

it('sends no start_at when --full is passed, even with prior synced history', function () {
    fakeTraktHistory([], []);

    Media::create([
        'occurred_at' => now()->subDays(30)->format('Y-m-d H:i:s'),
        'timezone' => 'Europe/London',
        'type' => 'episode',
        'title' => 'Old Episode',
        'source' => 'trakt',
        'source_id' => 'old-3',
        'meta' => ['season' => 1, 'episode' => 1],
    ]);

    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/history/movies')) {
            return false;
        }

        return ! array_key_exists('start_at', $request->data());
    });
});

/**
 * History and ratings are polled on very different cadences (every minute
 * versus daily), so each flag must do strictly its own half: if the frequent
 * run still paged the ratings library the split would buy nothing, and if the
 * daily one still imported history the two schedules could race to create the
 * same row.
 */
it('skips the ratings endpoints with --skip-ratings', function () {
    fakeTraktHistory([], []);

    $this->artisan('trakt:sync --skip-ratings')->assertSuccessful();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/ratings/'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/history/'));
});

it('skips the history endpoints with --ratings-only', function () {
    fakeTraktHistory([], []);

    $this->artisan('trakt:sync --ratings-only')->assertSuccessful();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/history/'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/ratings/'));
});
