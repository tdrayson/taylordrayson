<?php

namespace App\Http\Controllers;

use App\Queries\FlightMapData;
use App\Support\OgMeta;
use Inertia\Inertia;
use Inertia\Response;

class FlightMapController extends Controller
{
    /**
     * Full-screen globe of every flight, with per-year stat buckets.
     */
    public function __invoke(FlightMapData $data): Response
    {
        return Inertia::render('Flights/Map', [...$data(), 'og' => OgMeta::flightMap()]);
    }
}
