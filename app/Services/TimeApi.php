<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Client for the TimeAPI.io timezone service.
 *
 * Used as the coordinate-based fallback for resolving an IANA timezone when a
 * richer source (e.g. the aviation API) has no answer.
 */
class TimeApi
{
    private const BASE = 'https://timeapi.io';

    /**
     * The IANA timezone name for a coordinate, or null when it cannot be resolved.
     */
    public function timezoneForCoordinate(float $latitude, float $longitude): ?string
    {
        $response = Http::get(self::BASE.'/api/timezone/coordinate', [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        return $response->successful() ? $response->json('timeZone') : null;
    }
}
