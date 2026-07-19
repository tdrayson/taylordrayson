<?php

namespace App\Console\Commands\Media;

use App\Jobs\EnrichMedia;
use App\Models\Media;
use App\Models\Series;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:enrich {--force : Re-enrich everything, not just bare items}')]
#[Description('Re-dispatch TMDB enrichment for media missing artwork/metadata')]
class EnrichMediaCommand extends Command
{
    /**
     * Backfill command for `TraktSync`'s per-run enrichment dispatch: catches
     * series/films left bare because a prior `EnrichMedia` job never ran, or
     * exhausted its retries, outside of the sync window that created them.
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

            EnrichMedia::dispatch($series, 'tv', $series->meta['ids']['tmdb'] ?? null, null);
            $dispatched++;
        });

        return $dispatched;
    }

    private function enrichFilms(bool $force): int
    {
        $dispatched = 0;

        Media::query()->where('source', 'trakt')->where('type', 'film')->get()
            ->each(function (Media $media) use ($force, &$dispatched): void {
                if (! $force && ! $this->mediaIsBare($media)) {
                    return;
                }

                EnrichMedia::dispatch($media, 'movie', $media->meta['ids']['tmdb'] ?? null, null);
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
        return ! $series->hasMedia('cover') || empty($series->meta['tmdb']);
    }

    private function mediaIsBare(Media $media): bool
    {
        return ! $media->hasMedia('cover') || empty($media->meta['tmdb']);
    }
}
