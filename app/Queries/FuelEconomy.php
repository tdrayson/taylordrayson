<?php

namespace App\Queries;

use App\Models\Fuel;

/**
 * Fuel economy for the tank a fill starts, using the leading method: miles
 * driven until the next fill divided by the imperial gallons put in here.
 * Null for the most recent fill (no next fill yet) or when either odometer
 * reading is missing.
 */
final class FuelEconomy
{
    /**
     * @return array{miles: int|null, mpg: float|null, vehicle: string|null}
     */
    public function __invoke(Fuel $fuel): array
    {
        return [
            ...$this->rangeAndMpg($fuel),
            'vehicle' => $this->vehicleLabel($fuel),
        ];
    }

    /**
     * @return array{miles: int|null, mpg: float|null}
     */
    private function rangeAndMpg(Fuel $fuel): array
    {
        $next = Fuel::query()
            ->where('vehicle_id', $fuel->vehicle_id)
            ->where('occurred_at', '>', $fuel->occurred_at)
            ->orderBy('occurred_at')
            ->first();

        if ($next === null || $fuel->odometer === null || $next->odometer === null) {
            return ['miles' => null, 'mpg' => null];
        }

        $miles = (int) $next->odometer - (int) $fuel->odometer;

        if ($miles <= 0 || ! $fuel->litres) {
            return ['miles' => null, 'mpg' => null];
        }

        $gallons = (float) $fuel->litres / 4.54609;

        return ['miles' => $miles, 'mpg' => round($miles / $gallons, 1)];
    }

    private function vehicleLabel(Fuel $fuel): ?string
    {
        $label = trim(($fuel->vehicle['make'] ?? '').' '.($fuel->vehicle['model'] ?? ''));

        return $label !== '' ? $label : null;
    }
}
