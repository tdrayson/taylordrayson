<?php

namespace App\Queries\Lookups;

use App\Models\Airport;

/**
 * Airports for the flight form. Local: 9,000-odd rows already sit in the
 * database, so this needs no third-party API and works offline.
 */
final class AirportLookup
{
    /**
     * @return list<array{value: string, label: string, detail: string|null}>
     */
    public function __invoke(string $query): array
    {
        return Airport::query()
            ->whereNotNull('iata_code')
            ->when($query !== '', fn ($builder) => $builder->where(
                fn ($inner) => $inner->where('iata_code', 'like', "{$query}%")
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhere('city', 'like', "%{$query}%"),
            ))
            // An exact code match is almost always what was meant, so it sorts
            // above the name and city matches rather than below them.
            ->orderByRaw('CASE WHEN iata_code = ? THEN 0 ELSE 1 END', [strtoupper($query)])
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn (Airport $airport): array => [
                'value' => $airport->iata_code,
                'label' => "{$airport->iata_code} — {$airport->name}",
                'detail' => trim(implode(', ', array_filter([$airport->city, $airport->country]))) ?: null,
            ])
            ->all();
    }
}
