<?php

namespace Database\Factories;

use App\Models\Fuel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fuel>
 */
class FuelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $litres = round(fake()->randomFloat(2, 30, 55), 2);
        $pricePerLitre = round(fake()->randomFloat(2, 1.40, 1.80), 2);
        $cost = round($litres * $pricePerLitre, 2);

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'vehicle_id' => 'hn14wxp',
            'litres' => $litres,
            'cost' => $cost,
            'price_per_litre' => $pricePerLitre,
            'odometer' => fake()->numberBetween(20000, 80000),
            'fuel_station_id' => null,
        ];
    }
}
