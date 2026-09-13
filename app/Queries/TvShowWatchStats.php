<?php

namespace App\Queries;

use App\Data\TvShowStats;
use App\Models\TvEpisode;
use App\Models\TvShow;
use Carbon\CarbonInterface;

/**
 * How much of a show has been watched, and over what stretch of time.
 *
 * A Query rather than methods on TvShow: every figure here walks the whole
 * `episodes` relation and folds it, which is the multi-step computation a model
 * is meant to stay out of.
 */
final class TvShowWatchStats
{
    public function __invoke(TvShow $tvShow): TvShowStats
    {
        $watched = $this->distinctEpisodesWatched($tvShow);

        return new TvShowStats(
            episodesWatched: $watched,
            seasons: $tvShow->meta->seasons,
            progress: self::progressFor($tvShow->meta->airedEpisodes, $watched),
            watchSpan: $this->watchSpan($tvShow),
            totalHours: round($this->totalRuntimeMinutes($tvShow) / 60),
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
    private function distinctEpisodesWatched(TvShow $tvShow): int
    {
        return $tvShow->episodes
            ->map(fn (TvEpisode $episode): string => ($episode->meta->season ?? '?').'x'.($episode->meta->episode ?? '?'))
            ->unique()
            ->count();
    }

    /** Every watch counts here, rewatches included: it is time actually spent. */
    private function totalRuntimeMinutes(TvShow $tvShow): int
    {
        return (int) $tvShow->episodes->sum(fn (TvEpisode $episode): int => $episode->meta->runtime ?? 0);
    }

    /** Human span between first and last watch, e.g. "8 months". */
    private function watchSpan(TvShow $tvShow): ?string
    {
        $first = $this->firstWatchedAt($tvShow);
        $last = $this->lastWatchedAt($tvShow);

        if (! $first || ! $last) {
            return null;
        }

        return $first->isSameDay($last)
            ? 'in a single day'
            : $first->diffForHumans($last, ['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1]);
    }

    private function firstWatchedAt(TvShow $tvShow): ?CarbonInterface
    {
        return $tvShow->episodes->min('occurred_at');
    }

    private function lastWatchedAt(TvShow $tvShow): ?CarbonInterface
    {
        return $tvShow->episodes->max('occurred_at');
    }
}
