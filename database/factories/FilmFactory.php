<?php

namespace Database\Factories;

use App\Models\Film;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Film>
 */
class FilmFactory extends Factory
{
    protected $model = Film::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'title' => fake()->words(fake()->numberBetween(2, 4), true),
            'rating' => fake()->optional(0.7)->numberBetween(1, 10),
            'meta' => [
                'year' => fake()->numberBetween(1990, 2026),
                'runtime' => fake()->numberBetween(80, 200),
                'genres' => fake()->randomElements(
                    ['Drama', 'Action', 'Comedy', 'Thriller', 'Sci-Fi', 'Horror', 'Romance', 'Documentary'],
                    fake()->numberBetween(1, 3),
                ),
            ],
        ];
    }
}
