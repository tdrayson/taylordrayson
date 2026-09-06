<?php

namespace App\Services\LogoStream;

use App\Support\Distance;

/**
 * Client for the LogoStream APIs.
 *
 * Wraps two hosts that share a single API key (config('services.logostream.key')):
 * the airline-logo host for raster airline icons/logos, and the aviation host for
 * route metadata.
 */
class Client
{
    public function __construct(
        private readonly AirlineLogosConnector $logos,
        private readonly AviationConnector $aviation,
    ) {}

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
        $response = $this->logos->send(new AirlineLogoRequest($iata, $variant, $size));

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
     * @return array{duration: int|null, departure_timezone: string|null, arrival_timezone: string|null, distance: int|null}|null
     *                                                                                                                            Null when the route is unknown or the request fails.
     */
    public function route(string $departureIata, string $arrivalIata): ?array
    {
        $response = $this->aviation->send(new RouteRequest($departureIata, $arrivalIata));

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
            'distance' => isset($route['distance_km']) ? Distance::fromKm($route['distance_km']) : null,
        ];
    }
}
