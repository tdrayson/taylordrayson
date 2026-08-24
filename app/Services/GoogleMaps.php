<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client for Google Places and Geocoding, used by the location field. Google
 * rather than Mapbox here because Mapbox ranks streets above businesses, so
 * venue searches returned the road instead. Mapbox still renders the maps.
 */
class GoogleMaps
{
    /**
     * The legacy Text Search, not Places API (New): the new one is not enabled
     * on this project, and this returns what the field needs anyway.
     */
    private const PLACES = 'https://maps.googleapis.com/maps/api/place/textsearch/json';

    private const NEARBY = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';

    /** Wide enough to reach the venue you are standing outside, not the next town. */
    private const NEARBY_RADIUS_METRES = 500;

    private const GEOCODE = 'https://maps.googleapis.com/maps/api/geocode/json';

    /**
     * Places matching a search, biased to a position when one is known.
     *
     * @return list<array{name: string, address: string|null, street: string|null, postcode: string|null, city: string|null, country: string|null, latitude: float, longitude: float}>
     */
    public function search(string $query, ?float $latitude = null, ?float $longitude = null): array
    {
        // Text Search needs words. With a position but nothing typed the answer
        // is "what is around here", which is a different endpoint.
        if (trim($query) === '') {
            return $latitude !== null && $longitude !== null
                ? $this->nearby($latitude, $longitude)
                : [];
        }

        $parameters = ['query' => $query, 'key' => $this->key()];

        if ($latitude !== null && $longitude !== null) {
            // A bias, not a restriction: somewhere further away still shows, it
            // just ranks below what is nearby.
            $parameters['location'] = "{$latitude},{$longitude}";
            $parameters['radius'] = 50000;
        }

        $response = Http::api()->get(self::PLACES, $parameters);

        if ($response->failed()) {
            return [];
        }

        return array_values(array_map(
            fn (array $place): array => $this->place($place),
            $response->json('results') ?? [],
        ));
    }

    /**
     * The named places around a position, ranked by distance: what the locate
     * button offers when nothing has been typed to search for.
     *
     * @return list<array{name: string, address: string|null, latitude: float|null, longitude: float|null}>
     */
    private function nearby(float $latitude, float $longitude): array
    {
        $response = Http::api()->get(self::NEARBY, [
            'location' => "{$latitude},{$longitude}",
            'radius' => self::NEARBY_RADIUS_METRES,
            'key' => $this->key(),
        ]);

        if ($response->failed()) {
            return [];
        }

        return array_values(array_map(
            fn (array $place): array => $this->place($place),
            $response->json('results') ?? [],
        ));
    }

    /**
     * The place at a coordinate, for the locate button.
     *
     * @return array{name: string, address: string|null, street: string|null, postcode: string|null, city: string|null, country: string|null, latitude: float, longitude: float}|null
     */
    public function reverse(float $latitude, float $longitude): ?array
    {
        $response = Http::api()->get(self::GEOCODE, [
            'latlng' => "{$latitude},{$longitude}",
            'key' => $this->key(),
        ]);

        $result = $response->successful() ? ($response->json('results.0') ?? null) : null;

        if ($result === null) {
            return null;
        }

        $components = $this->components($result['address_components'] ?? [], 'long_name', 'types');

        return [
            // A reverse lookup has no venue name, so the street stands in.
            'name' => $components['street'] ?? ($result['formatted_address'] ?? ''),
            'address' => $result['formatted_address'] ?? null,
            'street' => $components['street'] ?? null,
            'postcode' => $components['postcode'] ?? null,
            'city' => $components['city'] ?? null,
            'country' => $components['country'] ?? null,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * @param  array<string, mixed>  $place
     * @return array{name: string, address: string|null, street: string|null, postcode: string|null, city: string|null, country: string|null, latitude: float, longitude: float}
     */
    private function place(array $place): array
    {
        // Text Search returns no address components, so the parts are resolved
        // by reverse-geocoding the coordinates when a result is picked, rather
        // than by an extra call per row in a list nobody may choose from.
        return [
            'name' => $place['name'] ?? '',
            // Nearby Search names it `vicinity`, Text Search `formatted_address`.
            'address' => $place['formatted_address'] ?? $place['vicinity'] ?? null,
            'street' => null,
            'postcode' => null,
            'city' => null,
            'country' => null,
            'latitude' => (float) data_get($place, 'geometry.location.lat', 0),
            'longitude' => (float) data_get($place, 'geometry.location.lng', 0),
        ];
    }

    /**
     * Pull the parts an entry stores out of Google's component list. The two
     * APIs name the same fields differently, hence the key arguments.
     *
     * @param  array<int, array<string, mixed>>  $components
     * @return array{street: string|null, postcode: string|null, city: string|null, country: string|null}
     */
    private function components(array $components, string $textKey, string $typesKey): array
    {
        $of = function (string $type) use ($components, $textKey, $typesKey): ?string {
            foreach ($components as $component) {
                if (in_array($type, $component[$typesKey] ?? [], true)) {
                    return $component[$textKey] ?? null;
                }
            }

            return null;
        };

        // The street line only: the town, postcode and country have their own
        // fields, so repeating them in the address duplicates the same facts.
        $street = trim(implode(' ', array_filter([$of('street_number'), $of('route')])));

        return [
            'street' => $street !== '' ? $street : null,
            'postcode' => $of('postal_code'),
            'city' => $of('postal_town') ?? $of('locality'),
            'country' => $of('country'),
        ];
    }

    private function key(): string
    {
        $key = config('services.google.maps_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Google Maps key is not configured (GOOGLE_MAPS_API_KEY).');
        }

        return $key;
    }
}
