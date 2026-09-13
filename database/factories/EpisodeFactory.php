<?php

namespace Database\Factories;

use App\Models\Episode;
use App\Models\Series;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Episode>
 */
class EpisodeFactory extends Factory
{
    protected $model = Episode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'series_id' => Series::factory(),
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'title' => fake()->words(fake()->numberBetween(2, 4), true),
            'rating' => fake()->optional(0.7)->numberBetween(1, 10),
            'meta' => [
                'show_title' => fake()->words(fake()->numberBetween(2, 4), true),
                'season' => fake()->numberBetween(1, 8),
                'episode' => fake()->numberBetween(1, 24),
                'runtime' => fake()->numberBetween(25, 65),
            ],
        ];
    }
}
