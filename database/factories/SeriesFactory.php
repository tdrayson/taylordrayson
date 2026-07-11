<?php

namespace Database\Factories;

use App\Models\Series;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Series>
 */
class SeriesFactory extends Factory
{
    protected $model = Series::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(fake()->numberBetween(1, 3), true);

        return [
            'trakt_id' => fake()->unique()->numberBetween(1, 999999),
            'slug' => Str::slug($title),
            'title' => Str::title($title),
            'year' => fake()->numberBetween(1990, 2026),
            'overview' => fake()->sentence(),
            'meta' => ['aired_episodes' => fake()->numberBetween(10, 120), 'seasons' => fake()->numberBetween(1, 8)],
        ];
    }
}
