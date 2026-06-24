<?php

namespace Database\Factories;

use App\Models\FuelStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelStation>
 */
class FuelStationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => sprintf(
                '%s %s',
                fake()->randomElement(['Shell', 'BP', 'Esso', 'Tesco', "Sainsbury's"]),
                fake()->streetSuffix(),
            ),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'latitude' => fake()->latitude(49.8, 58.7),
            'longitude' => fake()->longitude(-8.6, 1.8),
        ];
    }
}
