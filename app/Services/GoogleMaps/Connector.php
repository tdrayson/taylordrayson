<?php

namespace App\Services\GoogleMaps;

use App\Services\ApiConnector;
use RuntimeException;

/** The Google Maps Places and Geocoding APIs, which share a host and a key. */
class Connector extends ApiConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://maps.googleapis.com/maps/api';
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        $key = config('services.google.maps_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Google Maps key is not configured (GOOGLE_MAPS_API_KEY).');
        }

        return ['key' => $key];
    }
}
