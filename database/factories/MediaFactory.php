<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['film', 'tv_episode', 'book']);

        $meta = match ($type) {
            'film' => [
                'year' => fake()->numberBetween(1990, 2026),
                'runtime' => fake()->numberBetween(80, 200),
                'genres' => fake()->randomElements(
                    ['Drama', 'Action', 'Comedy', 'Thriller', 'Sci-Fi', 'Horror', 'Romance', 'Documentary'],
                    fake()->numberBetween(1, 3),
                ),
            ],
            'tv_episode' => [
                'show_title' => fake()->words(fake()->numberBetween(2, 4), true),
                'season_number' => fake()->numberBetween(1, 8),
                'episode_number' => fake()->numberBetween(1, 24),
                'episode_title' => fake()->words(fake()->numberBetween(2, 4), true),
                'runtime' => fake()->numberBetween(25, 65),
            ],
            'book' => [
                'author' => fake()->name(),
                'isbn' => fake()->isbn13(),
            ],
        };

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'type' => $type,
            'title' => fake()->words(fake()->numberBetween(2, 4), true),
            'rating' => fake()->optional(0.7)->numberBetween(1, 10),
            'meta' => $meta,
        ];
    }
}
