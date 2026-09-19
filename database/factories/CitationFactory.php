<?php

namespace Database\Factories;

use App\Models\Citation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Citation> */
class CitationFactory extends Factory
{
    protected $model = Citation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'url' => 'https://example.com/'.fake()->unique()->slug(),
            'site' => 'example.com',
            'title' => fake()->sentence(4),
            'author_name' => fake()->name(),
            'excerpt' => fake()->paragraph(),
            'published_at' => now()->subDay(),
            'published_timezone' => '+00:00',
            'fetched_at' => now(),
        ];
    }
}
