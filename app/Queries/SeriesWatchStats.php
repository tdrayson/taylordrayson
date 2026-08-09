<?php

namespace App\Queries;

use App\Data\SeriesStats;
use App\Models\Media;
use App\Models\Series;
use Carbon\CarbonInterface;

/**
 * How much of a show has been watched, and over what stretch of time.
 *
 * A Query rather than methods on Series: every figure here walks the whole
 * `episodes` relation and folds it, which is the multi-step computation a model
 * is meant to stay out of.
 */
final class SeriesWatchStats
{
    public function __invoke(Series $series): SeriesStats
    {
        $watched = $this->distinctEpisodesWatched($series);

        return new SeriesStats(
            episodesWatched: $watched,
            seasons: $series->meta['seasons'] ?? null,
            progress: self::progressFor($series->meta['aired_episodes'] ?? null, $watched),
            watchSpan: $this->watchSpan($series),
            totalHours: round($this->totalRuntimeMinutes($series) / 60),
        );
    }

    /**
     * Percentage of aired episodes watched, clamped to 100.
     *
     * Static and taking the count rather than the show, because the index page
     * aggregates distinct watches for every show in one query and must not
     * hydrate `episodes` per row to reuse this. Null when the aired total is
     * unknown, so the bar is hidden rather than drawn at a made-up value.
     */
    public static function progressFor(?int $airedEpisodes, int $distinctWatched): ?int
    {
        if (! $airedEpisodes) {
            return null;
        }

        return (int) min(100, round($distinctWatched / $airedEpisodes * 100));
    }

    /**
     * Distinct season+episode pairs, so rewatching one episode five times does
     * not read as five episodes of progress.
     */
    private function distinctEpisodesWatched(Series $series): int
    {
        return $series->episodes
            ->map(fn (Media $episode): string => ($episode->meta['season'] ?? '?').'x'.($episode->meta['episode'] ?? '?'))
            ->unique()
            ->count();
    }

    /** Every watch counts here, rewatches included: it is time actually spent. */
    private function totalRuntimeMinutes(Series $series): int
    {
        return (int) $series->episodes->sum(fn (Media $episode): int => (int) ($episode->meta['runtime'] ?? 0));
    }

    /** Human span between first and last watch, e.g. "8 months". */
    private function watchSpan(Series $series): ?string
    {
        $first = $this->firstWatchedAt($series);
        $last = $this->lastWatchedAt($series);

        if (! $first || ! $last) {
            return null;
        }

        return $first->isSameDay($last)
            ? 'in a single day'
            : $first->diffForHumans($last, ['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1]);
    }

    private function firstWatchedAt(Series $series): ?CarbonInterface
    {
        return $series->episodes->min('occurred_at');
    }

    private function lastWatchedAt(Series $series): ?CarbonInterface
    {
        return $series->episodes->max('occurred_at');
    }
}
