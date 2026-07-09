<?php

namespace App\Actions\Fuel;

use App\Models\FuelStation;

class ResolveFuelStation
{
    /**
     * Find or create a station by name so fuel logs can reference stations
     * without pre-registering them. Matching is case-insensitive on name;
     * any newly supplied detail (brand, address, city, country, lat/lng)
     * fills gaps on the existing record but never overwrites a stored value.
     *
     * Note: Name matching is ASCII case-insensitive only via SQLite LOWER(),
     * which diverges from mb_strtolower() on non-ASCII characters. This is
     * acceptable for UK station names but should be reviewed if international
     * support is needed.
     *
     * @param  array{name: string, brand?: ?string, address?: ?string, city?: ?string, country?: ?string, latitude?: ?float, longitude?: ?float}  $attributes
     */
    public function __invoke(array $attributes): FuelStation
    {
        $name = trim($attributes['name']);
        $attributes['name'] = $name;

        $station = FuelStation::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($station === null) {
            return FuelStation::create($attributes);
        }

        $station->fill(
            collect($attributes)
                ->except('name')
                ->filter(fn ($value, string $key): bool => $value !== null && $station->getAttribute($key) === null)
                ->all()
        )->save();

        return $station->refresh();
    }
}
