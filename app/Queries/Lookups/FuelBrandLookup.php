<?php

namespace App\Queries\Lookups;

use App\Models\Fuel;
use App\Services\PetrolPrices\FuelBrands;

/**
 * Fuel brands, from the canonical list the app keeps, since the brand spelling is
 * what picks a card's logo. Brands already recorded are merged in, so an
 * independent typed once is offered again.
 */
final class FuelBrandLookup
{
    /**
     * @return list<array{value: string, label: string, detail: string|null}>
     */
    public function __invoke(string $query): array
    {
        $brands = collect(FuelBrands::names())
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
