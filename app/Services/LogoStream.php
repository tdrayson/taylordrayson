<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Client for the LogoStream APIs.
 *
 * Wraps two hosts that share a single API key (config('services.logostream.key')):
 * the airline-logo host for raster airline icons/logos, and the aviation host for
 * route metadata.
 */
class LogoStream
{
    private const BASE = 'https://airlines-api.logostream.dev';

    private const AVIATION_BASE = 'https://aviation-api.logostream.dev';

    /**
     * Fetch a single airline logo variant as raw image bytes, keyed by IATA code.
     *
     * LogoStream returns a generated SVG placeholder (x-asset "-") when it has no
     * real logo, so anything but a resolved raster asset is reported as
     * unavailable rather than saved.
     *
     * @return array{status: 'saved'|'unavailable'|'error', body: string|null}
     */
    public function airlineLogo(string $iata, string $variant, int $size = 400): array
    {
        $response = Http::get(self::BASE.'/airlines/iata/'.$iata, [
            'key' => config('services.logostream.key'),
            'variant' => $variant,
            'format' => 'png',
            'size' => $size,
        ]);

        if (! $response->successful()) {
            return ['status' => 'error', 'body' => null];
        }

        $asset = (string) $response->header('x-asset');
        $contentType = (string) $response->header('content-type');

        if ($asset === '' || $asset === '-' || ! str_starts_with($contentType, 'image/')) {
            return ['status' => 'unavailable', 'body' => null];
        }

        return ['status' => 'saved', 'body' => $response->body()];
    }

    /**
     * Route metadata (duration, timezones, distance) for a departure/arrival pair.
     *
     * @return array{duration: int|null, departure_timezone: string|null, arrival_timezone: string|null, distance_miles: int|null}|null
     *                                                                                                                                  Null when the route is unknown or the request fails.
     */
    public function route(string $departureIata, string $arrivalIata): ?array
    {
        $response = Http::withHeaders(['x-api-key' => config('services.logostream.key')])
            ->get(self::AVIATION_BASE.'/v1/routes', [
                'departureIata' => $departureIata,
                'arrivalIata' => $arrivalIata,
                'limit' => 1,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $route = $response->json('data.0');

        if (! $route) {
            return null;
        }

        return [
            'duration' => isset($route['duration_min']) ? (int) $route['duration_min'] * 60 : null,
            'departure_timezone' => $route['departure_timezone'] ?? null,
            'arrival_timezone' => $route['arrival_timezone'] ?? null,
            'distance_miles' => isset($route['distance_km']) ? (int) round($route['distance_km'] * 0.621371) : null,
        ];
    }
}
