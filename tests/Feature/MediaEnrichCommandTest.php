<?php

use App\Jobs\EnrichMedia;
use App\Models\Media;
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
function attachCover(Media|Series $subject): void
{
    Storage::fake(config('media-library.disk_name'));

    $bytes = file_get_contents(base_path('tests/Fixtures/pixel.webp'));
    $subject->addMediaFromString($bytes)->usingFileName('cover.webp')->toMediaCollection('cover');
}

it('dispatches enrichment only for bare series/films by default, and for everything with --force', function () {
    // (a) Bare series: has an episode (so it's picked up by whereHas), no
    // cover media, no meta.tmdb.
    $bareSeries = Series::factory()->create(['meta' => ['aired_episodes' => 10, 'seasons' => 1]]);
    Media::factory()->create(['type' => 'episode', 'series_id' => $bareSeries->id]);

    // (b) Bare trakt film: no cover media, no meta.tmdb.
    $bareFilm = Media::factory()->create([
        'type' => 'film',
        'source' => 'trakt',
        'meta' => ['ids' => ['trakt' => 9, 'tmdb' => 438631]],
    ]);

    // (c) Already-enriched series: has BOTH a cover and meta.tmdb, so it's
    // non-bare (bare = missing cover OR missing tmdb).
    $enrichedSeries = Series::factory()->create(['meta' => ['aired_episodes' => 10, 'seasons' => 1, 'tmdb' => ['id' => 1]]]);
    Media::factory()->create(['type' => 'episode', 'series_id' => $enrichedSeries->id]);
    attachCover($enrichedSeries);

    $this->artisan('media:enrich')->assertSuccessful();

    Bus::assertDispatched(EnrichMedia::class, 2);

    Bus::fake();
    $this->artisan('media:enrich', ['--force' => true])->assertSuccessful();

    Bus::assertDispatched(EnrichMedia::class, 3);
});

it('skips series with no episodes even when bare', function () {
    Series::factory()->create(['meta' => ['aired_episodes' => 10, 'seasons' => 1]]);

    $this->artisan('media:enrich')->assertSuccessful();

    Bus::assertNotDispatched(EnrichMedia::class);
});

it('only considers trakt-sourced films, not other media sources', function () {
    Media::factory()->create(['type' => 'film', 'source' => 'manual', 'meta' => []]);

    $this->artisan('media:enrich')->assertSuccessful();

    Bus::assertNotDispatched(EnrichMedia::class);
});
