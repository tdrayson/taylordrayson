<?php

namespace App\Actions\Fuel;

use App\Models\Fuel;

class CreateFuel
{
    public function __construct(private DeriveFuelFigures $derive) {}

    /**
     * Litres and price per litre derive from each other and the cost.
     *
     * @param  array{occurred_at?: string|null, litres?: float, cost: float, price_per_litre?: float|null, station_name?: string|null, brand?: string|null, address?: string|null, postcode?: string|null, city?: string|null, county?: string|null, country?: string|null, latitude?: float|null, longitude?: float|null, fuel_card_cost?: float|null, odometer?: int|null}  $attributes
     */
    public function __invoke(array $attributes): Fuel
    {
        $attributes = ($this->derive)($attributes);

        return Fuel::create([
            ...$attributes,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
            'vehicle_id' => $attributes['vehicle_id'] ?? self::defaultVehicleId(),
            // NOT NULL, and nothing is derivable from a zero cost and price.
            'litres' => $attributes['litres'] ?? 0.0,
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
