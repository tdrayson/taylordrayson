<?php

namespace Database\Factories;

use App\Models\Podcast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Podcast>
 */
class PodcastFactory extends Factory
{
    /**
     * @var array<int, string>
     */
    private const TOPICS = [
        'Business',
        'Side Projects',
        'AI',
        'Web Development',
        'Marketing',
        'Productivity',
        'Design',
        'Freelancing',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $topics = fake()->randomElements(self::TOPICS, fake()->numberBetween(1, 3));

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'season_number' => fake()->numberBetween(1, 7),
            'episode_number' => fake()->numberBetween(1, 50),
            'topic' => implode(', ', $topics),
            'duration' => fake()->numberBetween(1800, 5400),
            'show_notes' => fake()->paragraphs(3, true),
            'audio_url' => null,
            'video_url' => null,
        ];
    }
}
