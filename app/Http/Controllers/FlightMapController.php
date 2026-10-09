<?php

namespace App\Http\Controllers;

use App\Presenters\Heads\SiteHeads;
use App\Queries\FlightMapData;
use App\Support\Head;
use Inertia\Inertia;
use Inertia\Response;

class FlightMapController extends Controller
{
    /**
     * Full-screen globe of every flight, with per-year stat buckets.
     */
    public function __invoke(FlightMapData $data): Response
    {
        app(Head::class)->set(SiteHeads::flightMap());

        return Inertia::render('Flights/Map', $data());
    }
}
