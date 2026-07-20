<?php

namespace App\Http\Controllers;

use App\Models\Series;
use App\Queries\SeriesShowData;
use App\Queries\WatchedSeriesIndex;
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
        ]);
    }

    /**
     * The whole show: episodes grouped by season, then by watch-date within
     * each season, alongside the series-wide watch stats.
     */
    public function show(Series $series): Response
    {
        return Inertia::render('Media/SeriesShow', ($this->seriesShowData)($series)->toArray());
    }
}
