<?php

use App\Jobs\EnrichFromTmdb;
use App\Models\Episode;
use App\Models\Film;
use App\Models\Series;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Bus::fake();
});

/**
 * Attaches a real single-file `cover` media item so a subject is genuinely
 * non-bare by the `hasMedia('cover')` half of the bare check.
 */
function attachCover(Film|Series $subject): void
{
    Storage::fake(config('media-library.disk_name'));

    $bytes = file_get_contents(base_path('tests/Fixtures/pixel.webp'));
    $subject->addMediaFromString($bytes)->usingFileName('cover.webp')->toMediaCollection('cover');
}

it('dispatches enrichment only for bare series/films by default, and for everything with --force', function () {
    // (a) Bare series: has an episode (so it's picked up by whereHas), no
    // cover media, no meta.tmdb.
    $bareSeries = Series::factory()->create(['meta' => ['aired_episodes' => 10, 'seasons' => 1]]);
    Episode::factory()->create(['series_id' => $bareSeries->id]);

    // (b) Bare trakt film: no cover media, no meta.tmdb.
    $bareFilm = Film::factory()->create([
        'source' => 'trakt',
        'meta' => ['ids' => ['trakt' => 9, 'tmdb' => 438631]],
    ]);

    // (c) Already-enriched series: has BOTH a cover and meta.tmdb, so it's
    // non-bare (bare = missing cover OR missing tmdb).
    $enrichedSeries = Series::factory()->create(['meta' => ['aired_episodes' => 10, 'seasons' => 1, 'tmdb' => ['id' => 1]]]);
    Episode::factory()->create(['series_id' => $enrichedSeries->id]);
    attachCover($enrichedSeries);

    $this->artisan('tmdb:enrich')->assertSuccessful();

    Bus::assertDispatched(EnrichFromTmdb::class, 2);

    Bus::fake();
    $this->artisan('tmdb:enrich', ['--force' => true])->assertSuccessful();

    Bus::assertDispatched(EnrichFromTmdb::class, 3);
});

it('skips series with no episodes even when bare', function () {
    Series::factory()->create(['meta' => ['aired_episodes' => 10, 'seasons' => 1]]);

    $this->artisan('tmdb:enrich')->assertSuccessful();

    Bus::assertNotDispatched(EnrichFromTmdb::class);
});

it('only considers trakt-sourced films, not other media sources', function () {
    Film::factory()->create(['source' => 'manual', 'meta' => []]);

    $this->artisan('tmdb:enrich')->assertSuccessful();

    Bus::assertNotDispatched(EnrichFromTmdb::class);
});
