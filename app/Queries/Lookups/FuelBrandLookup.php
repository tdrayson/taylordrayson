<?php

namespace App\Queries\Lookups;

use App\Models\Fuel;
use App\Services\PetrolFinder;

/**
 * Fuel brands, from the canonical list the fuel API publishes.
 *
 * Free text let "Shell", "shell" and "Shell UK" all through, and the brand is
 * what picks the logo on a fuel card: a spelling the logo map does not know
 * renders as no logo at all.
 *
 * The brands already recorded are merged in, and stand alone when the fuel API
 * is unreachable. A dropdown with nothing in it is worse than free text, and
 * that API has been observed returning 404s.
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
