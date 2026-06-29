<?php

namespace Database\Factories;

use App\Models\Airline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Airline>
 */
class AirlineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'iata_code' => strtoupper($this->faker->unique()->lexify('??')),
            'icao_code' => strtoupper($this->faker->unique()->lexify('???')),
            'name' => $this->faker->company().' Airlines',
            'country' => $this->faker->country(),
        ];
    }
}
