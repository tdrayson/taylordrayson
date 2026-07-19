<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Series;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SeriesController extends Controller
{
    /**
     * Poster grid of every show with at least one watched episode, ordered by
     * the most recently watched episode first.
     *
     * Avoids hydrating every show's full `episodes`/`media` collections:
     * the sort key comes from a `MAX(occurred_at)` aggregate, and progress
     * is computed from one lean query over just `series_id`/`meta` rather
     * than loading each episode row and its relations.
     */
    public function index(): Response
    {
        $shows = Series::query()
            ->whereHas('episodes')
            ->withMax('episodes', 'occurred_at')
            ->get()
            ->sortByDesc('episodes_max_occurred_at')
            ->values();

        $distinctWatchedBySeriesId = $this->distinctWatchedEpisodeCounts($shows->pluck('id'));

        $series = $shows->map(fn (Series $show): array => [
            'slug' => $show->slug,
            'title' => $show->title,
            'year' => $show->year,
            'poster' => $show->getFirstMediaUrl('cover', 'card') ?: null,
            'progress' => $show->progressFromDistinct($distinctWatchedBySeriesId->get($show->id, 0)),
        ]);

        return Inertia::render('Media/SeriesIndex', [
            'series' => $series,
        ]);
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

    /**
     * The whole show: episodes grouped by season, then by watch-date within
     * each season, alongside the series-wide watch stats.
     */
    public function show(Series $series): Response
    {
        $series->load('episodes');

        return Inertia::render('Media/SeriesShow', [
            'series' => [
                'slug' => $series->slug,
                'title' => $series->title,
                'year' => $series->year,
                'overview' => $series->overview,
                'poster' => $series->getFirstMediaUrl('cover', 'card') ?: null,
                'backdrop' => $series->getFirstMediaUrl('backdrop') ?: null,
                'logo' => $series->getFirstMediaUrl('logo') ?: null,
                'network' => $series->meta['tmdb']['network'] ?? null,
                'rating' => $series->meta['rating'] ?? null,
                'platformUrl' => $series->meta['ids']['slug'] ?? null
                    ? "https://trakt.tv/shows/{$series->meta['ids']['slug']}"
                    : null,
            ],
            'stats' => $this->stats($series),
            'seasons' => $this->seasons($series->episodes),
            'seasonList' => $this->seasonList($series),
        ]);
    }

    /**
     * @return array{episodesWatched: int, seasons: int|null, progress: int|null, watchSpan: string|null, totalHours: float}
     */
    private function stats(Series $series): array
    {
        return [
            'episodesWatched' => $series->watchedEpisodeCount(),
            'seasons' => $series->meta['seasons'] ?? null,
            'progress' => $series->progress(),
            'watchSpan' => $series->watchSpan(),
            'totalHours' => round($series->totalRuntimeMinutes() / 60),
        ];
    }

    /**
     * TMDB's season structure (`meta.season_list`), camelCased for the show
     * page's season overview. Independent of which episodes we've actually
     * watched, unlike seasons()/groupByWatchDate() below.
     *
     * @return array<int, array{number: ?int, name: ?string, episodeCount: ?int, airDate: ?string}>
     */
    private function seasonList(Series $series): array
    {
        return collect($series->meta['season_list'] ?? [])
            ->map(fn (array $season): array => [
                'number' => $season['number'] ?? null,
                'name' => $season['name'] ?? null,
                'episodeCount' => $season['episode_count'] ?? null,
                'airDate' => $season['air_date'] ?? null,
            ])
            ->all();
    }

    /**
     * Group episodes by season, then by watch-date (Y-m-d) within each
     * season, so the show page can anchor each date group for the timeline
     * collapse link (`#watch-{date}`).
     *
     * @param  Collection<int, Media>  $episodes
     * @return array<int, array{season: int, dates: array<int, array{date: string, anchor: string, episodes: array<int, array<string, mixed>>}>}>
     */
    private function seasons(Collection $episodes): array
    {
        return $episodes
            ->groupBy(fn (Media $episode): int => (int) ($episode->meta['season'] ?? 0))
            ->map(fn (Collection $group, int $season): array => [
                'season' => $season,
                'dates' => $this->groupByWatchDate($group),
            ])
            ->sortBy('season')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Media>  $episodes
     * @return array<int, array{date: string, anchor: string, episodes: array<int, array<string, mixed>>}>
     */
    private function groupByWatchDate(Collection $episodes): array
    {
        return $episodes
            ->groupBy(fn (Media $episode): string => $episode->occurred_at->format('Y-m-d'))
            ->map(fn (Collection $group, string $date): array => [
                'date' => $date,
                'anchor' => "watch-{$date}",
                'episodes' => $group->map(fn (Media $episode): array => [
                    'id' => $episode->id,
                    'season' => $episode->meta['season'] ?? null,
                    'episode' => $episode->meta['episode'] ?? null,
                    'title' => $episode->title,
                    'occurredAt' => $episode->occurred_at->toIso8601String(),
                    'rating' => $episode->rating,
                    'url' => $episode->url(),
                ])->values()->all(),
            ])
            ->sortBy('date')
            ->values()
            ->all();
    }
}
