<?php

namespace App\Actions\Fuel;

use App\Models\Fuel;

class CreateFuel
{
    /**
     * Price per litre is derived when not given, so a fill-up entered from a
     * receipt needs only the two figures printed on it.
     *
     * @param  array{occurred_at?: string|null, litres: float, cost: float, price_per_litre?: float|null, station_name?: string|null, brand?: string|null, address?: string|null, postcode?: string|null, city?: string|null, county?: string|null, country?: string|null, latitude?: float|null, longitude?: float|null, fuel_card_cost?: float|null, odometer?: int|null}  $attributes
     */
    public function __invoke(array $attributes): Fuel
    {
        $litres = (float) $attributes['litres'];
        $cost = (float) $attributes['cost'];

        return Fuel::create([
            ...$attributes,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
            'vehicle_id' => $attributes['vehicle_id'] ?? self::defaultVehicleId(),
            'price_per_litre' => $attributes['price_per_litre']
                ?? ($litres > 0 ? round($cost / $litres, 3) : null),
        ]);
    }

    /**
     * The car in use, from config/vehicles.php. There is effectively one, so a
     * fill-up entered without naming it belongs to whichever is still active.
     */
    private static function defaultVehicleId(): ?string
    {
        foreach (config('vehicles', []) as $id => $vehicle) {
            if ($vehicle['active'] ?? false) {
                return (string) $id;
            }
        }

        return null;
    }
}
