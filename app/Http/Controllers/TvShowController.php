<?php

namespace App\Http\Controllers;

use App\Models\TvShow;
use App\Presenters\Heads\SiteHeads;
use App\Queries\TvShowData;
use App\Queries\WatchedTvShowsIndex;
use App\Support\Head;
use Inertia\Inertia;
use Inertia\Response;

class TvShowController extends Controller
{
    public function __construct(
        private readonly WatchedTvShowsIndex $watchedTvShowsIndex,
        private readonly TvShowData $tvShowData,
    ) {}

    /**
     * Poster grid of every show with at least one watched episode.
     */
    public function index(): Response
    {
        app(Head::class)->set(SiteHeads::tvShows());

        return Inertia::render('TvShows/Index', [
            'shows' => ($this->watchedTvShowsIndex)(),
        ]);
    }

    /**
     * The whole show: episodes grouped by season, then by watch-date within
     * each season, alongside the show-wide watch stats.
     */
    public function show(TvShow $tvShow): Response
    {
        $data = ($this->tvShowData)($tvShow);

        app(Head::class)->set(SiteHeads::tvShow(
            $data->show->title,
            $data->stats->episodesWatched,
            $data->stats->seasons,
            $data->stats->watchSpan,
            $data->show->backdrop ?: $data->show->poster,
        ));

        return Inertia::render('TvShows/Show', $data->toArray());
    }
}
