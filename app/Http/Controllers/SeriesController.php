<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Series;
use Carbon\CarbonInterface;
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
            ->with(['episodes', 'media'])
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

        abort_if($episodes->isEmpty(), 404);

        return Inertia::render('Media/SeriesSeason', [
            'series' => [
                'slug' => $series->slug,
                'title' => $series->title,
            ],
            'season' => $season,
            'stats' => $this->seasonStats($episodes),
            'dates' => $this->groupByWatchDate($episodes),
        ]);
    }

    /**
     * Every watch instance of one episode (rewatches included), ordered
     * oldest to newest.
     */
    public function episode(Series $series, int $season, int $episode): Response
    {
        $series->load('episodes');

        $watches = $series->episodes
            ->filter(fn (Media $watch): bool => (int) ($watch->meta['season'] ?? null) === $season
                && (int) ($watch->meta['episode'] ?? null) === $episode)
            ->values()
            ->map(fn (Media $watch): array => [
                'id' => $watch->id,
                'occurredAt' => $watch->occurred_at->toIso8601String(),
                'title' => $watch->title,
                'rating' => $watch->rating,
            ]);

        abort_if($watches->isEmpty(), 404);

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
     * Watch stats scoped to a single season's episodes, mirroring the
     * series-wide stats() shape without the series-level `seasons`/`progress`
     * fields, which don't make sense at season scope.
     *
     * @param  Collection<int, Media>  $episodes
     * @return array{episodesWatched: int, watchSpan: string|null, totalHours: float}
     */
    private function seasonStats(Collection $episodes): array
    {
        return [
            'episodesWatched' => $episodes
                ->map(fn (Media $episode): string => ($episode->meta['season'] ?? '?').'x'.($episode->meta['episode'] ?? '?'))
                ->unique()
                ->count(),
            'watchSpan' => $this->watchSpanFor($episodes),
            'totalHours' => round($episodes->sum(fn (Media $episode): int => (int) ($episode->meta['runtime'] ?? 0)) / 60),
        ];
    }

    /**
     * Human-readable span between the first and last watch in the given
     * collection, mirroring Series::watchSpan() but scoped to a subset of
     * episodes (e.g. a single season) rather than the whole series.
     *
     * @param  Collection<int, Media>  $episodes
     */
    private function watchSpanFor(Collection $episodes): ?string
    {
        $first = $episodes->min('occurred_at');
        $last = $episodes->max('occurred_at');
        if (! $first || ! $last) {
            return null;
        }

        return $first->isSameDay($last)
            ? 'in a single day'
            : 'over '.$first->diffForHumans($last, ['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1]);
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
                ])->values()->all(),
            ])
            ->sortBy('date')
            ->values()
            ->all();
    }
}
