<?php

namespace App\Queries\Lookups;

use App\Models\Airline;

/**
 * Airlines for the flight form, keyed on ICAO because that is what the flight
 * row stores.
 */
final class AirlineLookup
{
    /**
     * @return list<array{value: string, label: string, detail: string|null}>
     */
    public function __invoke(string $query): array
    {
        return Airline::query()
            ->whereNotNull('icao_code')
            ->when($query !== '', fn ($builder) => $builder->where(
                fn ($inner) => $inner->where('name', 'like', "%{$query}%")
                    ->orWhere('iata_code', 'like', "{$query}%")
                    ->orWhere('icao_code', 'like', "{$query}%"),
            ))
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn (Airline $airline): array => [
                'value' => $airline->icao_code,
                'label' => $airline->name,
                'detail' => $airline->iata_code ?: $airline->icao_code,
            ])
            ->all();
    }
}
