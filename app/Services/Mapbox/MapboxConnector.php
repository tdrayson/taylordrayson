<?php

namespace App\Services\Mapbox;

use App\Services\ApiConnector;

/** The Mapbox Geocoding API. */
class MapboxConnector extends ApiConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://api.mapbox.com/geocoding/v5/mapbox.places';
    }
}
