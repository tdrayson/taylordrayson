<?php

namespace App\Timeline;

/**
 * Named feed bundles addressable by `?filter=` on any feed URL. Each preset is a
 * curated set of TypeRegistry keys; the matching slug stays out of the URL so a
 * shared preset link (e.g. /feed/rss?filter=writing) is stable and cache-friendly
 * even as the underlying type set evolves.
 */
class FeedPresets
{
    /**
     * @return array<string, array{label: string, description: string, types: array<int, string>}>
     */
    public static function all(): array
    {
        return [
            'curated' => [
                'label' => 'Curated',
                'description' => 'My highlights. The good stuff, minus the 3am sleep logs.',
                'types' => ['note', 'article', 'project', 'media', 'podcast', 'appearance'],
            ],
            'everything' => [
                'label' => 'Everything',
                'description' => 'The whole kitchen sink. Every last thing I track.',
                'types' => array_keys(TypeRegistry::all()),
            ],
            'writing' => [
                'label' => 'Writing',
                'description' => "Notes, articles, and the projects I'm tinkering with.",
                'types' => ['note', 'article', 'project'],
            ],
            'watching' => [
                'label' => 'Watching & Listening',
                'description' => 'Films, telly, books, and the podcast.',
                'types' => ['media', 'podcast'],
            ],
            'travel' => [
                'label' => 'Travel',
                'description' => "Flights, places I've been, and petrol stops.",
                'types' => ['flight', 'checkin', 'fuel'],
            ],
            'health' => [
                'label' => 'Life & Health',
                'description' => "Workouts, sleep, and what I've been eating.",
                'types' => ['activity', 'sleep', 'calorie'],
            ],
        ];
    }

    /**
     * The type keys for a preset, or null when the preset key is unknown.
     *
     * @return array<int, string>|null
     */
    public static function types(string $key): ?array
    {
        return self::all()[$key]['types'] ?? null;
    }
}
