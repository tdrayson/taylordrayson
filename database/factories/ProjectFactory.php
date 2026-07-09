<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @var array<int, string>
     */
    private const PROJECT_NAMES = [
        'Tailwind Dashboard',
        'Laravel Invoicing',
        'Vue Component Library',
        'API Gateway',
        'Deploy CLI',
        'Markdown Editor',
        'Status Page',
        'Link Shortener',
        'Expense Tracker',
        'Blog Engine',
        'Newsletter Tool',
        'Booking System',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->randomElement(self::PROJECT_NAMES);
        $occurredAt = fake()->dateTimeBetween('-6 months');

        return [
            'occurred_at' => $occurredAt,
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->sentence(),
            'url' => fake()->optional(0.5)->url(),
            'github_url' => fake()->optional(0.6)->url(),
            'status' => fake()->randomElement(['active', 'maintained', 'archived', 'on_hold']),
            'featured' => fake()->boolean(20),
        ];
    }
}
