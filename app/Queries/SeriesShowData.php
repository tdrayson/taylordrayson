<?php

namespace App\Queries;

use App\Data\EpisodeRow;
use App\Data\SeasonGroup;
use App\Data\SeriesHeader;
use App\Data\SeriesShow;
use App\Data\WatchDateGroup;
use App\Models\Media;
use App\Models\Series;
use App\Support\TraktUrl;
use Illuminate\Support\Collection;

/**
 * Assembles the whole show page: header, watch stats, watched episodes grouped
 * by season then watch-date, and TMDB's season overview.
 */
final class SeriesShowData
{
    public function __construct(private readonly SeriesWatchStats $watchStats) {}

    public function __invoke(Series $series): SeriesShow
    {
        $series->load('episodes');

        return new SeriesShow(
            series: $this->header($series),
            stats: ($this->watchStats)($series),
            seasons: $this->seasons($series->episodes),
            seasonList: $series->meta->seasonList,
        );
    }

    private function header(Series $series): SeriesHeader
    {
        return new SeriesHeader(
            slug: $series->slug,
            title: $series->title,
            year: $series->year,
            overview: $series->overview,
            poster: $series->getFirstMediaUrl('cover', 'card') ?: null,
            backdrop: $series->optimisedUrl('backdrop'),
            logo: $series->optimisedUrl('logo'),
            network: $series->meta->tmdb->network,
            rating: $series->meta->rating,
            platformUrl: TraktUrl::show($series->meta->ids->slug),
        );
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
            ->groupBy(fn (Media $episode): int => $episode->meta->season ?? 0)
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
                    season: $episode->meta->season,
                    episode: $episode->meta->episode,
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
