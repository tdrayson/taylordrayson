<?php

namespace Database\Factories;

use App\Models\Appearance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appearance>
 */
class AppearanceFactory extends Factory
{
    /**
     * @var array<int, string>
     */
    private const SHOW_NAMES = [
        'The Laravel Podcast',
        'Syntax FM',
        'Shop Talk Show',
        'Full Stack Radio',
        'Laravel News Podcast',
        'PHP Roundtable',
        'DevDiscuss',
        'Indie Hackers',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'type' => fake()->randomElement(['podcast', 'livestream', 'interview', 'talk']),
            'title' => fake()->sentence(fake()->numberBetween(3, 8)),
            'show_name' => fake()->randomElement(self::SHOW_NAMES),
            'duration_seconds' => fake()->numberBetween(1800, 5400),
            'url' => fake()->optional(0.7)->url(),
        ];
    }
}
