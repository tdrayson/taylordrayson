<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

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
                'author' => fake()->name(),
                'isbn' => fake()->isbn13(),
            ],
        ];
    }
}
