<?php

namespace App\Queries\Lookups;

use App\Models\Fuel;
use App\Services\PetrolFinder;
use App\Services\PetrolFinder\FuelStationResult;

/**
 * Petrol stations, from the fuel API rather than a general place search.
 *
 * It knows what a filling station is, which a geocoder does not: searching a
 * town returns the stations in it rather than the streets. It also returns the
 * brand and the address already split, so one pick fills the whole row.
 *
 * Stations already filled up at are offered too, and are all there is when the
 * fuel API is unreachable, which it has been observed to be. The same garage
 * twice is the common case anyway.
 */
final class StationLookup
{
    public function __construct(private PetrolFinder $stations) {}

    /**
     * @return list<array{value: string, label: string, detail: string|null, fill: array<string, mixed>}>
     */
    public function __invoke(string $query, ?float $latitude = null, ?float $longitude = null): array
    {
        if (trim($query) === '' && $latitude === null) {
            return [];
        }

        $results = $latitude !== null && $longitude !== null && trim($query) === ''
            ? $this->stations->search(null, $latitude, $longitude)
            : $this->stations->search($query);

        if ($results === []) {
            return $this->previouslyUsed($query);
        }

        return array_values(array_map(fn (FuelStationResult $station): array => [
            'value' => $station->stationName,
            'label' => $station->stationName,
            'detail' => trim(implode(', ', array_filter([$station->address, $station->postcode]))) ?: null,
            'fill' => array_filter([
                'brand' => $station->brand,
                'address' => $station->address,
                'postcode' => $station->postcode,
                'city' => $station->city,
                'latitude' => $station->latitude,
                'longitude' => $station->longitude,
            ], fn ($value): bool => $value !== null && $value !== ''),
        ], array_slice($results, 0, 10)));
    }

    /**
     * Garages already on record, matched by name.
     *
     * @return list<array{value: string, label: string, detail: string|null, fill: array<string, mixed>}>
     */
    private function previouslyUsed(string $query): array
    {
        return Fuel::query()
            ->whereNotNull('station_name')
            ->when($query !== '', fn ($builder) => $builder->where('station_name', 'like', "%{$query}%"))
            ->orderByDesc('occurred_at')
            ->get()
            ->unique('station_name')
            ->take(10)
            ->map(fn (Fuel $fuel): array => [
                'value' => $fuel->station_name,
                'label' => $fuel->station_name,
                'detail' => trim(implode(', ', array_filter([$fuel->address, $fuel->postcode]))) ?: 'Used before',
                'fill' => array_filter([
                    'brand' => $fuel->brand,
                    'address' => $fuel->address,
                    'postcode' => $fuel->postcode,
                    'city' => $fuel->city,
                    'latitude' => $fuel->latitude,
                    'longitude' => $fuel->longitude,
                ], fn ($value): bool => $value !== null && $value !== ''),
            ])
            ->values()
            ->all();
    }
}
