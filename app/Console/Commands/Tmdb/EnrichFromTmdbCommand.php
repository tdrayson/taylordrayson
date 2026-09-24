<?php

namespace App\Console\Commands\Tmdb;

use App\Jobs\EnrichFromTmdb;
use App\Models\Film;
use App\Models\TvShow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tmdb:enrich {--force : Re-enrich everything, not just bare items}')]
#[Description('Re-dispatch TMDB enrichment for films and TV shows missing artwork/metadata')]
class EnrichFromTmdbCommand extends Command
{
    /**
     * Backfill command for `TraktSync`'s per-run enrichment dispatch: catches
     * shows/films left bare because a prior `EnrichFromTmdb` job never ran,
     * or exhausted its retries, outside of the sync window that created them.
     */
    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $tvShowCount = $this->enrichTvShows($force);
        $filmCount = $this->enrichFilms($force);

        $this->info("Dispatched enrichment for {$tvShowCount} TV show(s) and {$filmCount} film(s).");

        return self::SUCCESS;
    }

    private function enrichTvShows(bool $force): int
    {
        $dispatched = 0;

        TvShow::query()->whereHas('episodes')->get()->each(function (TvShow $tvShow) use ($force, &$dispatched): void {
            if (! $force && ! $this->tvShowIsBare($tvShow)) {
                return;
            }

            EnrichFromTmdb::dispatch($tvShow, 'tv', $tvShow->meta->ids->tmdb, null);
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

    /** Mirrors `TraktSync::tvShowIsBare()`: missing its cover or backdrop. */
    private function tvShowIsBare(TvShow $tvShow): bool
    {
        return ! $tvShow->hasMedia('cover') || ! $tvShow->hasMedia('backdrop');
    }

    private function filmIsBare(Film $film): bool
    {
        return ! $film->hasMedia('cover') || $film->meta->tmdb->isEmpty();
    }
}
