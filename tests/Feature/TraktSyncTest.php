<?php

use App\Jobs\EnrichFromTmdb;
use App\Models\Film;
use App\Models\TvEpisode;
use App\Models\TvShow;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

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

    Saloon::fake(['*' => function ($pendingRequest) use ($movies, $episodes, $movieRatings, $episodeRatings, $showRatings) {
        return match (true) {
            str_contains($pendingRequest->getUrl(), '/history/movies') => MockResponse::make($pendingRequest->query()->get('page') == 1 ? $movies : [], 200),
            str_contains($pendingRequest->getUrl(), '/history/episodes') => MockResponse::make($pendingRequest->query()->get('page') == 1 ? $episodes : [], 200),
            str_contains($pendingRequest->getUrl(), '/ratings/movies') => MockResponse::make($pendingRequest->query()->get('page') == 1 ? $movieRatings : [], 200),
            str_contains($pendingRequest->getUrl(), '/ratings/episodes') => MockResponse::make($pendingRequest->query()->get('page') == 1 ? $episodeRatings : [], 200),
            str_contains($pendingRequest->getUrl(), '/ratings/shows') => MockResponse::make($pendingRequest->query()->get('page') == 1 ? $showRatings : [], 200),
            str_contains($pendingRequest->getUrl(), '/shows/') => MockResponse::make([
                'aired_episodes' => 20,
                'ids' => ['slug' => 'severance'],
                'title' => 'Severance',
                'images' => ['poster' => ['walter-r2.trakt.tv/posters/severance.jpg']],
            ], 200),
            default => MockResponse::make([], 200),
        };
    }]);
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
    expect(Film::count())->toBe(1)
        ->and(TvEpisode::count())->toBe(2)
        ->and(TvShow::count())->toBe(2) // two distinct shows despite identical title
        ->and(TvShow::pluck('slug')->sort()->values()->all())->toEqual(['the-office', 'the-office-2001']);

    // Re-run creates nothing new.
    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();
    expect(Film::count() + TvEpisode::count())->toBe(3);

    // One poster per new subject: the film (poster embedded in the history
    // item) and both new shows (poster resolved via the /shows/{id} summary
    // fallback, since the inline `show` payload carries no `images`).
    Bus::assertDispatched(EnrichFromTmdb::class, 3);
});

it('fails closed and stops importing when a history page request fails mid-pagination', function () {
    Saloon::fake(['' => function ($pendingRequest) {
        return match (true) {
            str_contains($pendingRequest->getUrl(), '/history/movies') => MockResponse::make($pendingRequest->query()->get('page') == 1 ? [[
                'id' => 501, 'watched_at' => '2024-01-01T20:00:00.000Z', 'action' => 'watch', 'type' => 'movie',
                'movie' => [
                    'title' => 'Dune', 'year' => 2021, 'runtime' => 155,
                    'ids' => ['trakt' => 9, 'slug' => 'dune-2021', 'tmdb' => 438631],
                    'images' => ['poster' => ['walter-r2.trakt.tv/posters/dune-2021.jpg']],
                ],
            ]] : [], 200),
            str_contains($pendingRequest->getUrl(), '/history/episodes') => MockResponse::make('server error', 500),
            default => MockResponse::make([], 200),
        };
    }]);

    $this->artisan('trakt:sync', ['--full' => true])->assertFailed();

    // The movies page succeeded and was imported before the episodes page
    // failed; the command still reports failure rather than silently
    // truncating the episode history.
    expect(Film::count())->toBe(1)
        ->and(TvEpisode::count())->toBe(0);
});

it('imports personal star ratings onto films, episodes, and tv shows, staying idempotent on re-run', function () {
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

    $film = Film::firstOrFail();
    $episode = TvEpisode::firstOrFail();
    $tvShow = TvShow::where('trakt_id', 700)->firstOrFail();

    expect($film->rating)->toBe(9)
        ->and($episode->rating)->toBe(8)
        ->and($tvShow->meta->rating)->toBe(10);

    // Re-run stays idempotent: same ratings applied again, nothing errors,
    // nothing changes.
    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    expect($film->fresh()->rating)->toBe(9)
        ->and($episode->fresh()->rating)->toBe(8)
        ->and($tvShow->fresh()->meta->rating)->toBe(10);
});

it('nudges episodes that share an exact watched_at into season/episode order, idempotently, and leaves distinct timestamps alone', function () {
    $tvShow = TvShow::factory()->create(['trakt_id' => 700]);

    // Normalization is scoped to tv shows synced this run (see the scoping
    // test below), so this run also imports one new episode of the same    // show (a different season/episode, on a different date, so it
    // doesn't collide with the seeded E1/E6/E7 tie below) purely to mark
    // the show "affected".
    fakeTraktHistory(movies: [], episodes: [
        ['id' => 900, 'watched_at' => '2026-01-05T12:00:00.000Z', 'action' => 'watch', 'type' => 'episode',
            'episode' => ['season' => 1, 'number' => 10, 'title' => 'Ten', 'runtime' => 50, 'ids' => ['trakt' => 910]],
            'show' => ['title' => 'Show Title', 'year' => 2020, 'ids' => ['trakt' => 700, 'slug' => 'show-slug', 'tmdb' => 2316]]],
    ]);

    // Real Trakt data: bulk-marking watched gives S1E6 and S1E7 the exact
    // same second, seeded here already out of order (E7 row created first).
    $e7 = TvEpisode::create([
        'occurred_at' => '2026-01-02 07:32:00',
        'title' => 'Seven',
        'tv_show_id' => $tvShow->id,
        'source' => 'trakt',
        'source_id' => 'e7',
        'meta' => ['season' => 1, 'episode' => 7],
    ]);
    $e6 = TvEpisode::create([
        'occurred_at' => '2026-01-02 07:32:00',
        'title' => 'Six',
        'tv_show_id' => $tvShow->id,
        'source' => 'trakt',
        'source_id' => 'e6',
        'meta' => ['season' => 1, 'episode' => 6],
    ]);
    $e1 = TvEpisode::create([
        'occurred_at' => '2026-01-01 20:00:00',
        'title' => 'One',
        'tv_show_id' => $tvShow->id,
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
    $tvShow = TvShow::factory()->create(['trakt_id' => 701]);

    // Normalization is scoped to tv shows synced this run, so this run also
    // imports one new episode of the same show (a different season/episode,
    // on a different date, so it doesn't collide with the seeded E1/E2/E3
    // tie below) purely to mark the show "affected".
    fakeTraktHistory(movies: [], episodes: [
        ['id' => 901, 'watched_at' => '2026-01-05T12:00:00.000Z', 'action' => 'watch', 'type' => 'episode',
            'episode' => ['season' => 1, 'number' => 10, 'title' => 'Ten', 'runtime' => 50, 'ids' => ['trakt' => 911]],
            'show' => ['title' => 'Show Title', 'year' => 2020, 'ids' => ['trakt' => 701, 'slug' => 'show-slug-2', 'tmdb' => 2317]]],
    ]);

    // All three share the exact same second, one second before midnight, and
    // are seeded out of (season, episode) order so the sort itself is exercised
    // too. Naively assigning base+rank would push E3 to 2026-01-03 00:00:01.
    $e3 = TvEpisode::create([
        'occurred_at' => '2026-01-02 23:59:59',
        'timezone' => 'Europe/London',
        'title' => 'Three',
        'tv_show_id' => $tvShow->id,
        'source' => 'trakt',
        'source_id' => 'e3',
        'meta' => ['season' => 1, 'episode' => 3],
    ]);
    $e1 = TvEpisode::create([
        'occurred_at' => '2026-01-02 23:59:59',
        'timezone' => 'Europe/London',
        'title' => 'One',
        'tv_show_id' => $tvShow->id,
        'source' => 'trakt',
        'source_id' => 'e1',
        'meta' => ['season' => 1, 'episode' => 1],
    ]);
    $e2 = TvEpisode::create([
        'occurred_at' => '2026-01-02 23:59:59',
        'timezone' => 'Europe/London',
        'title' => 'Two',
        'tv_show_id' => $tvShow->id,
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

it('scopes normalization to tv shows synced this run, leaving another shows tied timestamps unchanged', function () {
    $tvShowA = TvShow::factory()->create(['trakt_id' => 702]);
    $tvShowB = TvShow::factory()->create(['trakt_id' => 703]);

    // Show A already carries a tied group from a previous run; it receives
    // no new episode this run, so it must NOT be touched by normalize.
    $a1 = TvEpisode::create([
        'occurred_at' => '2026-01-02 07:32:00',
        'title' => 'A One',
        'tv_show_id' => $tvShowA->id,
        'source' => 'trakt',
        'source_id' => 'a1',
        'meta' => ['season' => 1, 'episode' => 1],
    ]);
    $a2 = TvEpisode::create([
        'occurred_at' => '2026-01-02 07:32:00',
        'title' => 'A Two',
        'tv_show_id' => $tvShowA->id,
        'source' => 'trakt',
        'source_id' => 'a2',
        'meta' => ['season' => 1, 'episode' => 2],
    ]);

    // Only show B receives a new episode this run.
    fakeTraktHistory(movies: [], episodes: [
        ['id' => 950, 'watched_at' => '2026-02-01T12:00:00.000Z', 'action' => 'watch', 'type' => 'episode',
            'episode' => ['season' => 1, 'number' => 1, 'title' => 'B One', 'runtime' => 50, 'ids' => ['trakt' => 951]],
            'show' => ['title' => 'Show B', 'year' => 2020, 'ids' => ['trakt' => 703, 'slug' => 'show-b', 'tmdb' => 3000]]],
    ]);

    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    // Show A wasn't affected this run, so its tied pair keeps sharing the
    // exact same timestamp instead of being nudged apart by a full-table scan.
    expect($a1->fresh()->occurred_at->toDateTimeString())->toBe('2026-01-02 07:32:00')
        ->and($a2->fresh()->occurred_at->toDateTimeString())->toBe('2026-01-02 07:32:00');
});

it('self-heals the sync window to the last synced watch when it is older than the default --days window', function () {
    fakeTraktHistory([], []);

    // Last synced watch is 30 days ago; default --days=7 would otherwise
    // miss the gap between day 7 and day 30 if a scheduled run was skipped.
    TvEpisode::create([
        'occurred_at' => now()->subDays(30)->format('Y-m-d H:i:s'),
        'timezone' => 'Europe/London',
        'title' => 'Old Episode',
        'source' => 'trakt',
        'source_id' => 'old-1',
        'meta' => ['season' => 1, 'episode' => 1],
    ]);

    $this->artisan('trakt:sync')->assertSuccessful();

    Saloon::assertSent(function ($request, $response) {
        if (! str_contains($response->getPendingRequest()->getUrl(), '/history/movies')) {
            return false;
        }

        $daysAgo = Carbon::parse($request->query()->get('start_at'))->diffInDays(now());

        return $daysAgo >= 29 && $daysAgo <= 31;
    });
});

it('caps the self-healed sync window at MAX_CATCHUP_DAYS when the last synced watch is much older', function () {
    fakeTraktHistory([], []);

    // Last synced watch is 200 days ago (e.g. sync was broken for months);
    // the window must be capped at MAX_CATCHUP_DAYS (90) rather than
    // requesting a huge, unbounded backfill.
    TvEpisode::create([
        'occurred_at' => now()->subDays(200)->format('Y-m-d H:i:s'),
        'timezone' => 'Europe/London',
        'title' => 'Very Old Episode',
        'source' => 'trakt',
        'source_id' => 'old-2',
        'meta' => ['season' => 1, 'episode' => 1],
    ]);

    $this->artisan('trakt:sync')->assertSuccessful();

    Saloon::assertSent(function ($request, $response) {
        if (! str_contains($response->getPendingRequest()->getUrl(), '/history/movies')) {
            return false;
        }

        $daysAgo = Carbon::parse($request->query()->get('start_at'))->diffInDays(now());

        return $daysAgo >= 89 && $daysAgo <= 91;
    });
});

it('re-dispatches enrichment for an existing bare tv show that receives a new episode this run', function () {
    // Bare: no cover or backdrop. One episode already exists so the show is
    // genuinely pre-existing, not created by this run.
    $tvShow = TvShow::factory()->create(['trakt_id' => 700]);
    TvEpisode::create([
        'occurred_at' => '2024-01-01 20:00:00',
        'title' => 'Existing',
        'tv_show_id' => $tvShow->id,
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

    // Not a new show, so the old `$wasNew`-only dispatch would have missed
    // this: the show is bare, so it must still re-enrich.
    Bus::assertDispatched(EnrichFromTmdb::class, 1);
});

it('does not re-enrich a show that already has its artwork, even without tmdb meta', function () {
    Storage::fake(config('media-library.disk_name'));

    $tvShow = TvShow::factory()->create(['trakt_id' => 700]);
    $pixel = file_get_contents(base_path('tests/Fixtures/pixel.webp'));
    $tvShow->addMediaFromString($pixel)->usingFileName('cover.webp')->toMediaCollection('cover');
    $tvShow->addMediaFromString($pixel)->usingFileName('backdrop.webp')->toMediaCollection('backdrop');

    fakeTraktHistory(movies: [], episodes: [
        ['id' => 601, 'watched_at' => '2024-02-01T20:00:00.000Z', 'action' => 'watch', 'type' => 'episode',
            'episode' => ['season' => 1, 'number' => 2, 'title' => 'New Ep', 'runtime' => 50, 'ids' => ['trakt' => 111]],
            'show' => ['title' => 'Formula 1', 'year' => 1950, 'ids' => ['trakt' => 700, 'slug' => 'formula-1', 'tmdb' => 327805]]],
    ]);

    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    Bus::assertNotDispatched(EnrichFromTmdb::class);
});

it('dispatches enrichment once for a bare tv show even when a batch carries several of its episodes', function () {
    $tvShow = TvShow::factory()->create(['trakt_id' => 700]);
    TvEpisode::create([
        'occurred_at' => '2024-01-01 20:00:00',
        'title' => 'Existing',
        'tv_show_id' => $tvShow->id,
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

    expect(TvEpisode::count())->toBe(4); // 1 pre-existing + 3 new

    // Deduped per run: three new episodes of the same bare show still only
    // trigger one enrichment dispatch.
    Bus::assertDispatched(EnrichFromTmdb::class, 1);
});

it('sends no start_at when --full is passed, even with prior synced history', function () {
    fakeTraktHistory([], []);

    TvEpisode::create([
        'occurred_at' => now()->subDays(30)->format('Y-m-d H:i:s'),
        'timezone' => 'Europe/London',
        'title' => 'Old Episode',
        'source' => 'trakt',
        'source_id' => 'old-3',
        'meta' => ['season' => 1, 'episode' => 1],
    ]);

    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    Saloon::assertSent(function ($request, $response) {
        if (! str_contains($response->getPendingRequest()->getUrl(), '/history/movies')) {
            return false;
        }

        return ! array_key_exists('start_at', $request->query()->all());
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

    Saloon::assertNotSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), '/ratings/'));
    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), '/history/'));
});

it('skips the history endpoints with --ratings-only', function () {
    fakeTraktHistory([], []);

    $this->artisan('trakt:sync --ratings-only')->assertSuccessful();

    Saloon::assertNotSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), '/history/'));
    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), '/ratings/'));
});

it('stores the trakt overview on films and episodes', function () {
    fakeTraktHistory(
        movies: [[
            'id' => 901, 'watched_at' => '2026-09-09T21:10:00.000Z', 'action' => 'watch', 'type' => 'movie',
            'movie' => [
                'title' => 'Fall 2: Deadpoint', 'year' => 2026, 'runtime' => 98,
                'overview' => 'Two climbers become trapped.',
                'ids' => ['trakt' => 1, 'slug' => 'fall-2', 'tmdb' => 2],
                'images' => ['poster' => ['walter-r2.trakt.tv/posters/fall-2.jpg']],
            ],
        ]],
        episodes: [[
            'id' => 902, 'watched_at' => '2026-09-10T18:59:00.000Z', 'action' => 'watch', 'type' => 'episode',
            'episode' => ['season' => 4, 'number' => 6, 'title' => "Don't Jump Around Much Anymore", 'runtime' => 47, 'overview' => "It's New Year's Eve!", 'ids' => ['trakt' => 3]],
            'show' => ['title' => 'Ted Lasso', 'year' => 2020, 'ids' => ['trakt' => 4, 'slug' => 'ted-lasso', 'tmdb' => 5]],
        ]],
    );

    $this->artisan('trakt:sync', ['--full' => true])->assertSuccessful();

    expect(Film::firstWhere('source_id', '901')->overview)->toBe('Two climbers become trapped.')
        ->and(TvEpisode::firstWhere('source_id', '902')->overview)->toBe("It's New Year's Eve!");
});
