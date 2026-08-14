<?php

namespace App\Queries\Lookups;

use App\Models\Fuel;
use App\Services\PetrolPrices;
use App\Services\PetrolPrices\FuelStationResult;

/**
 * Petrol stations, from the forecourt feed rather than a geocoder, which would
 * return a town's streets instead of its filling stations. Stations already used
 * are also offered, and are all there is when that feed is down.
 */
final class StationLookup
{
    public function __construct(private PetrolPrices $stations) {}

    /**
     * @return list<array{value: string, label: string, detail: string|null, fill: array<string, mixed>}>
     */
    public function __invoke(string $query, ?float $latitude = null, ?float $longitude = null): array
    {
        $query = trim($query);

        if ($query === '' && $latitude === null) {
            return [];
        }

        // The feed searches by coordinate alone, so typing narrows what is nearby.
        $results = $latitude !== null && $longitude !== null
            ? $this->matching($this->stations->search($latitude, $longitude), $query)
            : [];

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
     * The stations whose name, brand or address contains what was typed.
     *
     * @param  array<int, FuelStationResult>  $stations
     * @return array<int, FuelStationResult>
     */
    private function matching(array $stations, string $query): array
    {
        if ($query === '') {
            return $stations;
        }

        return array_values(array_filter($stations, function (FuelStationResult $station) use ($query): bool {
            $haystack = implode(' ', array_filter([$station->stationName, $station->brand, $station->address]));

            return str_contains(mb_strtolower($haystack), mb_strtolower($query));
        }));
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
