<?php

namespace App\Queries;

use App\Data\SeasonGroup;
use App\Data\TvEpisodeRow;
use App\Data\TvShowHeader;
use App\Data\TvShowPage;
use App\Data\WatchDateGroup;
use App\Models\TvEpisode;
use App\Models\TvShow;
use App\Support\TraktUrl;
use Illuminate\Support\Collection;

/**
 * Assembles the whole show page: header, watch stats, watched episodes grouped
 * by season then watch-date, and TMDB's season overview.
 */
final class TvShowData
{
    public function __construct(private readonly TvShowWatchStats $watchStats) {}

    public function __invoke(TvShow $tvShow): TvShowPage
    {
        $tvShow->load('episodes');

        return new TvShowPage(
            show: $this->header($tvShow),
            stats: ($this->watchStats)($tvShow),
            seasons: $this->seasons($tvShow->episodes),
            seasonList: $tvShow->meta->seasonList,
        );
    }

    private function header(TvShow $tvShow): TvShowHeader
    {
        return new TvShowHeader(
            slug: $tvShow->slug,
            title: $tvShow->title,
            year: $tvShow->year,
            overview: $tvShow->overview,
            poster: $tvShow->getFirstMediaUrl('cover', 'card') ?: null,
            backdrop: $tvShow->optimisedUrl('backdrop'),
            logo: $tvShow->optimisedUrl('logo'),
            network: $tvShow->meta->tmdb->network,
            rating: $tvShow->meta->rating,
            platformUrl: TraktUrl::show($tvShow->meta->ids->slug),
        );
    }

    /**
     * Group episodes by season, then by watch-date within each season.
     *
     * @param  Collection<int, TvEpisode>  $episodes
     * @return list<SeasonGroup>
     */
    private function seasons(Collection $episodes): array
    {
        return $episodes
            ->groupBy(fn (TvEpisode $episode): int => $episode->meta->season ?? 0)
            ->map(fn (Collection $group, int $season): SeasonGroup => new SeasonGroup(
                season: $season,
                dates: $this->groupByWatchDate($group),
            ))
            ->sortBy('season')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, TvEpisode>  $episodes
     * @return list<WatchDateGroup>
     */
    private function groupByWatchDate(Collection $episodes): array
    {
        return $episodes
            ->groupBy(fn (TvEpisode $episode): string => $episode->occurred_at->format('Y-m-d'))
            ->map(fn (Collection $group, string $date): WatchDateGroup => new WatchDateGroup(
                date: $date,
                anchor: "watch-{$date}",
                episodes: $group->map(fn (TvEpisode $episode): TvEpisodeRow => new TvEpisodeRow(
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
