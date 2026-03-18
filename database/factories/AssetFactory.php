<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-6 months');

        return [
            'type' => fake()->randomElement(['cover', 'photo', 'map']),
            'path' => $date->format('Y').'/'.$date->format('m').'/'.$date->format('d').'/'.Str::uuid().'.jpg',
            'original_filename' => fake()->word().'.jpg',
            'width' => fake()->randomElement([800, 1200, 1600, 1920]),
            'height' => fake()->randomElement([600, 800, 1080, 1200]),
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(50000, 5000000),
            'order' => 0,
        ];
    }
}
