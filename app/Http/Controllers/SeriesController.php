<?php

namespace App\Http\Controllers;

use App\Models\Series;
use App\Queries\SeriesShowData;
use App\Queries\WatchedSeriesIndex;
use App\Support\OgMeta;
use Inertia\Inertia;
use Inertia\Response;

class SeriesController extends Controller
{
    public function __construct(
        private readonly WatchedSeriesIndex $watchedSeriesIndex,
        private readonly SeriesShowData $seriesShowData,
    ) {}

    /**
     * Poster grid of every show with at least one watched episode.
     */
    public function index(): Response
    {
        return Inertia::render('Media/SeriesIndex', [
            'series' => ($this->watchedSeriesIndex)(),
            'og' => OgMeta::series(),
        ]);
    }

    /**
     * The whole show: episodes grouped by season, then by watch-date within
     * each season, alongside the series-wide watch stats.
     */
    public function show(Series $series): Response
    {
        $data = ($this->seriesShowData)($series);

        return Inertia::render('Media/SeriesShow', [
            ...$data->toArray(),
            'og' => OgMeta::seriesShow(
                $data->series->title,
                $data->stats->episodesWatched,
                $data->stats->seasons,
                $data->stats->watchSpan,
            ),
        ]);
    }
}
