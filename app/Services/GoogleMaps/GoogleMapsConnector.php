<?php

namespace App\Services\GoogleMaps;

use App\Services\ApiConnector;

/** The Google Maps Places and Geocoding APIs, which share a host and a key. */
class GoogleMapsConnector extends ApiConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://maps.googleapis.com/maps/api';
    }
}
