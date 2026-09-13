<?php

namespace Database\Factories;

use App\Models\TvEpisode;
use App\Models\TvShow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TvEpisode>
 */
class TvEpisodeFactory extends Factory
{
    protected $model = TvEpisode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tv_show_id' => TvShow::factory(),
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
