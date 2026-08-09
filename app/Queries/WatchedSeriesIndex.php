<?php

namespace App\Queries;

use App\Data\SeriesSummary;
use App\Models\Media;
use App\Models\Series;
use Illuminate\Support\Collection;

/**
 * Poster grid of every show with a watched episode, most recent first. Sorts on a
 * `MAX(occurred_at)` aggregate and computes progress from one lean query rather
 * than hydrating each show's full `episodes` collection.
 */
final class WatchedSeriesIndex
{
    /**
     * @return list<SeriesSummary>
     */
    public function __invoke(): array
    {
        $shows = Series::query()
            ->whereHas('episodes')
            ->withMax('episodes', 'occurred_at')
            ->with('media')
            ->get()
            ->sortByDesc('episodes_max_occurred_at')
            ->values();

        $distinctWatchedBySeriesId = $this->distinctWatchedEpisodeCounts($shows->pluck('id'));

        return $shows->map(fn (Series $show): SeriesSummary => new SeriesSummary(
            slug: $show->slug,
            title: $show->title,
            year: $show->year,
            poster: $show->getFirstMediaUrl('cover', 'card') ?: null,
            progress: SeriesWatchStats::progressFor(
                $show->meta['aired_episodes'] ?? null,
                $distinctWatchedBySeriesId->get($show->id, 0),
            ),
        ))->all();
    }

    /**
     * Distinct (season, episode) watched-count per series, in one query
     * (`series_id`/`meta` only, no relations), so rewatches don't inflate
     * progress without hydrating every episode row per show.
     *
     * @param  Collection<int, int>  $seriesIds
     * @return Collection<int, int> keyed by series id
     */
    private function distinctWatchedEpisodeCounts(Collection $seriesIds): Collection
    {
        return Media::query()
            ->whereIn('series_id', $seriesIds)
            ->get(['series_id', 'meta'])
            ->groupBy('series_id')
            ->map(fn (Collection $episodes): int => $episodes
                ->map(fn (Media $episode): string => ($episode->meta['season'] ?? '?').'x'.($episode->meta['episode'] ?? '?'))
                ->unique()
                ->count());
    }
}
