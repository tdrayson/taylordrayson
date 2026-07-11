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
     */
    public function index(): Response
    {
        $series = Series::query()
            ->whereHas('episodes')
            ->with('episodes')
            ->get()
            ->sortByDesc(fn (Series $show): ?string => $show->lastWatchedAt()?->toIso8601String())
            ->values()
            ->map(fn (Series $show): array => [
                'slug' => $show->slug,
                'title' => $show->title,
                'year' => $show->year,
                'poster' => $show->getFirstMediaUrl('cover', 'card') ?: null,
                'progress' => $show->progress(),
            ]);

        return Inertia::render('Media/SeriesIndex', [
            'series' => $series,
        ]);
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
                'platformUrl' => $series->meta['ids']['slug'] ?? null
                    ? "https://trakt.tv/shows/{$series->meta['ids']['slug']}"
                    : null,
            ],
            'stats' => $this->stats($series),
            'seasons' => $this->seasons($series->episodes),
        ]);
    }

    /**
     * A single season's watched episodes, plus stats scoped to that season.
     */
    public function season(Series $series, int $season): Response
    {
        $series->load('episodes');

        $episodes = $series->episodes->filter(
            fn (Media $episode): bool => (int) ($episode->meta['season'] ?? null) === $season
        )->values();

        return Inertia::render('Media/SeriesSeason', [
            'series' => [
                'slug' => $series->slug,
                'title' => $series->title,
            ],
            'season' => $season,
            'stats' => $this->stats($series),
            'dates' => $this->groupByWatchDate($episodes),
        ]);
    }

    /**
     * Every watch instance of one episode (rewatches included), ordered
     * oldest to newest.
     */
    public function episode(Series $series, int $season, int $episode): Response
    {
        $watches = Media::query()
            ->where('series_id', $series->id)
            ->where('meta->season', $season)
            ->where('meta->episode', $episode)
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (Media $watch): array => [
                'id' => $watch->id,
                'occurredAt' => $watch->occurred_at->toIso8601String(),
                'title' => $watch->meta['episode_title'] ?? $watch->title,
                'rating' => $watch->rating,
            ]);

        return Inertia::render('Media/SeriesEpisode', [
            'series' => [
                'slug' => $series->slug,
                'title' => $series->title,
            ],
            'season' => $season,
            'episode' => $episode,
            'watches' => $watches,
        ]);
    }

    /**
     * @return array{episodesWatched: int, seasons: int, progress: int|null, watchSpan: string|null, totalHours: float}
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
                    'title' => $episode->meta['episode_title'] ?? $episode->title,
                    'occurredAt' => $episode->occurred_at->toIso8601String(),
                    'rating' => $episode->rating,
                ])->values()->all(),
            ])
            ->sortBy('date')
            ->values()
            ->all();
    }
}
