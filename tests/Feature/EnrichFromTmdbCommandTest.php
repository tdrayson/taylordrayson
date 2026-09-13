<?php

use App\Jobs\EnrichFromTmdb;
use App\Models\Film;
use App\Models\TvEpisode;
use App\Models\TvShow;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Bus::fake();
});

/**
 * Attaches a real single-file media item to each collection so a subject is
 * genuinely non-bare.
 */
function attachArtwork(Film|TvShow $subject, string ...$collections): void
{
    Storage::fake(config('media-library.disk_name'));

    $bytes = file_get_contents(base_path('tests/Fixtures/pixel.webp'));

    foreach ($collections as $collection) {
        $subject->addMediaFromString($bytes)->usingFileName("{$collection}.webp")->toMediaCollection($collection);
    }
}

it('dispatches enrichment only for bare tv shows/films by default, and for everything with --force', function () {
    // (a) Bare tv show: has an episode (so it's picked up by whereHas), no
    // cover media, no meta.tmdb.
    $bareTvShow = TvShow::factory()->create(['meta' => ['aired_episodes' => 10, 'seasons' => 1]]);
    TvEpisode::factory()->create(['tv_show_id' => $bareTvShow->id]);

    // (b) Bare trakt film: no cover media, no meta.tmdb.
    $bareFilm = Film::factory()->create([
        'source' => 'trakt',
        'meta' => ['ids' => ['trakt' => 9, 'tmdb' => 438631]],
    ]);

    // (c) Already-enriched tv show: has BOTH a cover and a backdrop, so it's
    // non-bare (bare = missing cover OR missing backdrop).
    $enrichedTvShow = TvShow::factory()->create(['meta' => ['aired_episodes' => 10, 'seasons' => 1, 'tmdb' => ['id' => 1]]]);
    TvEpisode::factory()->create(['tv_show_id' => $enrichedTvShow->id]);
    attachArtwork($enrichedTvShow, 'cover', 'backdrop');

    $this->artisan('tmdb:enrich')->assertSuccessful();

    Bus::assertDispatched(EnrichFromTmdb::class, 2);

    Bus::fake();
    $this->artisan('tmdb:enrich', ['--force' => true])->assertSuccessful();

    Bus::assertDispatched(EnrichFromTmdb::class, 3);
});

it('skips tv shows with no episodes even when bare', function () {
    TvShow::factory()->create(['meta' => ['aired_episodes' => 10, 'seasons' => 1]]);

    $this->artisan('tmdb:enrich')->assertSuccessful();

    Bus::assertNotDispatched(EnrichFromTmdb::class);
});

it('only considers trakt-sourced films, not other media sources', function () {
    Film::factory()->create(['source' => 'manual', 'meta' => []]);

    $this->artisan('tmdb:enrich')->assertSuccessful();

    Bus::assertNotDispatched(EnrichFromTmdb::class);
});
