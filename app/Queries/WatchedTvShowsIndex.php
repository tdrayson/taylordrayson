<?php

namespace App\Queries;

use App\Data\TvEpisodeMeta;
use App\Data\TvShowSummary;
use App\Models\TvEpisode;
use App\Models\TvShow;
use Illuminate\Support\Collection;

/**
 * Poster grid of every show with a watched episode, most recent first. Sorts on a
 * `MAX(occurred_at)` aggregate and computes progress from one lean query rather
 * than hydrating each show's full `episodes` collection.
 */
final class WatchedTvShowsIndex
{
    /**
     * @return list<TvShowSummary>
     */
    public function __invoke(): array
    {
        $shows = TvShow::query()
            ->whereHas('episodes', fn ($episodes) => $episodes->listed())
            ->withMax(['episodes' => fn ($episodes) => $episodes->listed()], 'occurred_at')
            ->with('media')
            ->get()
            ->sortByDesc('episodes_max_occurred_at')
            ->values();

        $distinctWatchedByTvShowId = $this->distinctWatchedEpisodeCounts($shows->pluck('id'));

        return $shows->map(fn (TvShow $show): TvShowSummary => new TvShowSummary(
            slug: $show->slug,
            title: $show->title,
            year: $show->year,
            poster: $show->getFirstMediaUrl('cover', 'card') ?: null,
            progress: TvShowWatchStats::progressFor(
                $show->meta->airedEpisodes,
                $distinctWatchedByTvShowId->get($show->id, 0),
            ),
        ))->all();
    }

    /**
     * Distinct (season, episode) watched-count per show, in one query
     * (`tv_show_id`/`meta` only, no relations), so rewatches don't inflate
     * progress without hydrating every episode row per show.
     *
     * @param  Collection<int, int>  $tvShowIds
     * @return Collection<int, int> keyed by tv show id
     */
    private function distinctWatchedEpisodeCounts(Collection $tvShowIds): Collection
    {
        return TvEpisode::query()
            ->listed()
            ->whereIn('tv_show_id', $tvShowIds)
            // toBase() skips Eloquent hydration: this counts two numbers per
            // row across every episode ever watched, and has no use for a
            // model. TvEpisodeMeta still names the fields, so the season/episode
            // keys are spelled in one place rather than inline here.
            ->toBase()
            ->get(['tv_show_id', 'meta'])
            ->groupBy('tv_show_id')
            ->map(fn (Collection $episodes): int => $episodes
                ->map(function (object $episode): string {
                    $meta = TvEpisodeMeta::from(json_decode((string) $episode->meta, true));

                    return ($meta->season ?? '?').'x'.($meta->episode ?? '?');
                })
                ->unique()
                ->count());
    }
}
