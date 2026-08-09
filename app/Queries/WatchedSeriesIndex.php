<?php

namespace App\Queries;

use App\Data\MediaMeta;
use App\Data\SeriesSummary;
use App\Models\Media;
use App\Models\Series;
use Illuminate\Support\Collection;

/**
 * Poster grid of every show with at least one watched episode, ordered by the
 * most recently watched episode first.
 *
 * Avoids hydrating every show's full `episodes` collection: the sort key comes
 * from a `MAX(occurred_at)` aggregate, and progress is computed from one lean
 * query over just `series_id`/`meta` that builds no models at all. `media` is
 * still eager-loaded so poster resolution stays a single query, not one per
 * series.
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
                $show->meta->airedEpisodes,
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
            // toBase() skips Eloquent hydration: this counts two numbers per
            // row across every episode ever watched, and has no use for a
            // model. MediaMeta still names the fields, so the season/episode
            // keys are spelled in one place rather than inline here.
            ->toBase()
            ->get(['series_id', 'meta'])
            ->groupBy('series_id')
            ->map(fn (Collection $episodes): int => $episodes
                ->map(function (object $episode): string {
                    $meta = MediaMeta::from(json_decode((string) $episode->meta, true));

                    return ($meta->season ?? '?').'x'.($meta->episode ?? '?');
                })
                ->unique()
                ->count());
    }
}
