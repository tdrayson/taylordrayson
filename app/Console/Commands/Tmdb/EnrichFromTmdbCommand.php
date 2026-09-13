<?php

namespace App\Console\Commands\Tmdb;

use App\Jobs\EnrichFromTmdb;
use App\Models\Film;
use App\Models\Series;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tmdb:enrich {--force : Re-enrich everything, not just bare items}')]
#[Description('Re-dispatch TMDB enrichment for films and series missing artwork/metadata')]
class EnrichFromTmdbCommand extends Command
{
    /**
     * Backfill command for `TraktSync`'s per-run enrichment dispatch: catches
     * series/films left bare because a prior `EnrichFromTmdb` job never ran,
     * or exhausted its retries, outside of the sync window that created them.
     */
    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $seriesCount = $this->enrichSeries($force);
        $filmCount = $this->enrichFilms($force);

        $this->info("Dispatched enrichment for {$seriesCount} series and {$filmCount} film(s).");

        return self::SUCCESS;
    }

    private function enrichSeries(bool $force): int
    {
        $dispatched = 0;

        Series::query()->whereHas('episodes')->get()->each(function (Series $series) use ($force, &$dispatched): void {
            if (! $force && ! $this->seriesIsBare($series)) {
                return;
            }

            EnrichFromTmdb::dispatch($series, 'tv', $series->meta->ids->tmdb, null);
            $dispatched++;
        });

        return $dispatched;
    }

    private function enrichFilms(bool $force): int
    {
        $dispatched = 0;

        Film::query()->where('source', 'trakt')->get()
            ->each(function (Film $film) use ($force, &$dispatched): void {
                if (! $force && ! $this->filmIsBare($film)) {
                    return;
                }

                EnrichFromTmdb::dispatch($film, 'movie', $film->meta->ids->tmdb, null);
                $dispatched++;
            });

        return $dispatched;
    }

    /**
     * Mirrors `TraktSync::seriesIsBare()`: missing either the cover artwork
     * or the TMDB enrichment metadata block.
     */
    private function seriesIsBare(Series $series): bool
    {
        return ! $series->hasMedia('cover') || $series->meta->tmdb->isEmpty();
    }

    private function filmIsBare(Film $film): bool
    {
        return ! $film->hasMedia('cover') || $film->meta->tmdb->isEmpty();
    }
}
