<?php

namespace App\Stories;

use App\Http\Controllers\StoryController;

/**
 * The catalogue of data stories. Add a story's class here and it becomes
 * available at /stories/{its-slug} with no further wiring. Resolved by the
 * single {@see StoryController}.
 */
class StoryRegistry
{
    /** @var array<int, class-string<Story>> */
    private const STORIES = [
        FuelStory::class,
        FoodStory::class,
        FlightStory::class,
    ];

    /**
     * Every registered story, instantiated.
     *
     * @return array<int, Story>
     */
    public function all(): array
    {
        return array_map(fn (string $class): Story => app($class), self::STORIES);
    }

    /**
     * The story matching a slug, or null when none does.
     */
    public function find(string $slug): ?Story
    {
        foreach (self::STORIES as $class) {
            $story = app($class);

            if ($story->slug() === $slug) {
                return $story;
            }
        }

        return null;
    }
}
