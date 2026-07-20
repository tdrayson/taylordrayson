<?php

namespace App\Queries;

use App\Data\EpisodeRow;
use App\Data\SeasonGroup;
use App\Data\SeasonSummary;
use App\Data\SeriesHeader;
use App\Data\SeriesShow;
use App\Data\SeriesStats;
use App\Data\WatchDateGroup;
use App\Models\Media;
use App\Models\Series;
use Illuminate\Support\Collection;

/**
 * Assembles the whole show page: header, watch stats, watched episodes grouped
 * by season then watch-date, and TMDB's season overview.
 */
final class SeriesShowData
{
    public function __invoke(Series $series): SeriesShow
    {
        $series->load('episodes');

        return new SeriesShow(
            series: $this->header($series),
            stats: $this->stats($series),
            seasons: $this->seasons($series->episodes),
            seasonList: $this->seasonList($series),
        );
    }

    private function header(Series $series): SeriesHeader
    {
        $slug = $series->meta['ids']['slug'] ?? null;

        return new SeriesHeader(
            slug: $series->slug,
            title: $series->title,
            year: $series->year,
            overview: $series->overview,
            poster: $series->getFirstMediaUrl('cover', 'card') ?: null,
            backdrop: $series->getFirstMediaUrl('backdrop') ?: null,
            logo: $series->getFirstMediaUrl('logo') ?: null,
            network: $series->meta['tmdb']['network'] ?? null,
            rating: $series->meta['rating'] ?? null,
            platformUrl: $slug ? "https://trakt.tv/shows/{$slug}" : null,
        );
    }

    private function stats(Series $series): SeriesStats
    {
        return new SeriesStats(
            episodesWatched: $series->watchedEpisodeCount(),
            seasons: $series->meta['seasons'] ?? null,
            progress: $series->progress(),
            watchSpan: $series->watchSpan(),
            totalHours: round($series->totalRuntimeMinutes() / 60),
        );
    }

    /**
     * TMDB's season structure (`meta.season_list`), camelCased for the show
     * page's season overview. Independent of which episodes we've actually
     * watched, unlike seasons()/groupByWatchDate() below.
     *
     * @return list<SeasonSummary>
     */
    private function seasonList(Series $series): array
    {
        return collect($series->meta['season_list'] ?? [])
            ->map(fn (array $season): SeasonSummary => new SeasonSummary(
                number: $season['number'] ?? null,
                name: $season['name'] ?? null,
                episodeCount: $season['episode_count'] ?? null,
                airDate: $season['air_date'] ?? null,
            ))
            ->all();
    }

    /**
     * Group episodes by season, then by watch-date within each season.
     *
     * @param  Collection<int, Media>  $episodes
     * @return list<SeasonGroup>
     */
    private function seasons(Collection $episodes): array
    {
        return $episodes
            ->groupBy(fn (Media $episode): int => (int) ($episode->meta['season'] ?? 0))
            ->map(fn (Collection $group, int $season): SeasonGroup => new SeasonGroup(
                season: $season,
                dates: $this->groupByWatchDate($group),
            ))
            ->sortBy('season')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Media>  $episodes
     * @return list<WatchDateGroup>
     */
    private function groupByWatchDate(Collection $episodes): array
    {
        return $episodes
            ->groupBy(fn (Media $episode): string => $episode->occurred_at->format('Y-m-d'))
            ->map(fn (Collection $group, string $date): WatchDateGroup => new WatchDateGroup(
                date: $date,
                anchor: "watch-{$date}",
                episodes: $group->map(fn (Media $episode): EpisodeRow => new EpisodeRow(
                    id: $episode->id,
                    season: $episode->meta['season'] ?? null,
                    episode: $episode->meta['episode'] ?? null,
                    title: $episode->title,
                    occurredAt: $episode->occurred_at->toIso8601String(),
                    rating: $episode->rating,
                    url: $episode->url(),
                ))->values()->all(),
            ))
            ->sortBy('date')
            ->values()
            ->all();
    }
}
