<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client for the Mapbox Geocoding API, used for the location field: searching
 * a place by name, and turning the browser's coordinates into something
 * readable.
 *
 * The same MAPBOX_TOKEN already renders every stored map, so a place picked
 * here and the map generated for it come from one source.
 */
class Mapbox
{
    private const BASE = 'https://api.mapbox.com/geocoding/v5/mapbox.places';

    /**
     * Places matching a search, nearest-first when a position is supplied.
     *
     * @return list<array{name: string, address: string|null, latitude: float, longitude: float}>
     */
    public function search(string $query, ?float $latitude = null, ?float $longitude = null): array
    {
        if (trim($query) === '') {
            return [];
        }

        return $this->features($query, array_filter([
            'types' => 'poi,address,place',
            'limit' => 8,
            'proximity' => $latitude !== null && $longitude !== null ? "{$longitude},{$latitude}" : null,
        ], fn ($value): bool => $value !== null));
    }

    /**
     * The place at a coordinate, for the "use my location" button.
     *
     * @return array{name: string, address: string|null, latitude: float, longitude: float}|null
     */
    public function reverse(float $latitude, float $longitude): ?array
    {
        return $this->features("{$longitude},{$latitude}", ['types' => 'poi,address,place', 'limit' => 1])[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return list<array{name: string, address: string|null, latitude: float, longitude: float}>
     */
    private function features(string $query, array $parameters): array
    {
        $token = config('services.mapbox.token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Mapbox token is not configured (MAPBOX_TOKEN).');
        }

        $response = Http::api()->get(self::BASE.'/'.rawurlencode($query).'.json', [
            ...$parameters,
            'access_token' => $token,
        ]);

        if ($response->failed()) {
            return [];
        }

        return array_values(array_map(fn (array $feature): array => [
            'name' => $feature['text'] ?? ($feature['place_name'] ?? ''),
            'address' => $feature['place_name'] ?? null,
            'longitude' => (float) ($feature['center'][0] ?? 0),
            'latitude' => (float) ($feature['center'][1] ?? 0),
            ...$this->context($feature),
        ], $response->json('features') ?? []));
    }

    /**
     * Mapbox returns the town, postcode and country as a `context` list rather
     * than as fields. Pulling them out is what lets picking a place fill an
     * entry's city and postcode instead of only its coordinates.
     *
     * @param  array<string, mixed>  $feature
     * @return array{city: string|null, postcode: string|null, country: string|null}
     */
    private function context(array $feature): array
    {
        $of = function (string $prefix) use ($feature): ?string {
            foreach ($feature['context'] ?? [] as $entry) {
                if (str_starts_with((string) ($entry['id'] ?? ''), $prefix)) {
                    return $entry['text'] ?? null;
                }
            }

            return null;
        };

        return [
            'city' => $of('place') ?? $of('locality'),
            'postcode' => $of('postcode'),
            'country' => $of('country'),
        ];
    }
}
