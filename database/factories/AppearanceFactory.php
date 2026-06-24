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
     * Real YouTube video ids so derived thumbnails resolve for seeded data.
     *
     * @var array<int, string>
     */
    private const VIDEO_IDS = [
        'vD9nJ_1M3xY',
        'HcgaWNolZPU',
        '4BOXB7cJx6w',
        'Pg2Q7zpfG0Y',
        'W7rO_mZTuWM',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $videoId = fake()->randomElement(self::VIDEO_IDS);

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'type' => fake()->randomElement(['podcast', 'livestream', 'interview', 'talk']),
            'title' => fake()->sentence(fake()->numberBetween(3, 8)),
            'show_name' => fake()->randomElement(self::SHOW_NAMES),
            'duration' => fake()->numberBetween(1800, 5400),
            'url' => fake()->optional(0.7)->url(),
            'video_url' => "https://www.youtube.com/watch?v={$videoId}",
            'audio_url' => fake()->optional(0.3)->url(),
            'description' => fake()->optional(0.7)->paragraph(),
        ];
    }
}
