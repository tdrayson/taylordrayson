<?php

namespace App\Queries\Lookups;

use App\Models\Fuel;
use App\Services\PetrolFinder;

/**
 * Fuel brands, from the canonical list the fuel API publishes, since the brand
 * spelling is what picks a card's logo. Brands already recorded are merged in and
 * stand alone when that API is down, which it has been.
 */
final class FuelBrandLookup
{
    public function __construct(private PetrolFinder $stations) {}

    /**
     * @return list<array{value: string, label: string, detail: string|null}>
     */
    public function __invoke(string $query): array
    {
        $brands = collect($this->stations->brands())
            ->map(fn (array $brand): string => $brand['name'])
            ->merge(Fuel::query()->whereNotNull('brand')->distinct()->pluck('brand'))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($query !== '') {
            $brands = $brands->filter(
                fn (string $name): bool => str_contains(strtolower($name), strtolower($query)),
            )->values();
        }

        return $brands->take(20)
            ->map(fn (string $name): array => ['value' => $name, 'label' => $name, 'detail' => null])
            ->all();
    }
}
