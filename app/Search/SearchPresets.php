<?php

namespace App\Search;

use App\Support\Distance;

/**
 * Curated example searches shown on the empty search page. Each entry is a
 * ready-made filter in the exact shape the query builder produces, so clicking
 * one repopulates the builder and runs it through the normal search flow.
 */
class SearchPresets
{
    /**
     * @return array<int, array{key: string, label: string, description: string, filter: array<int, array{type: string, conditions: array<int, array{field: string, operator: string, value: mixed}>}>}>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'activities-with-photos',
                'label' => 'Activities with photos',
                'description' => 'Workouts I snapped a photo on',
                'filter' => [[
                    'type' => 'activity',
                    'conditions' => [
                        ['field' => 'photos', 'operator' => 'has_any', 'value' => null],
                    ],
                ]],
            ],
            [
                'key' => 'long-runs',
                'label' => 'Long runs',
                'description' => 'Runs of 5km or more',
                'filter' => [[
                    'type' => 'activity',
                    'conditions' => [
                        ['field' => 'kind', 'operator' => 'is', 'value' => ['run']],
                        ['field' => 'distance', 'operator' => 'gte', 'value' => (string) Distance::fromKm(5)],
                    ],
                ]],
            ],
            [
                'key' => 'hour-plus-workouts',
                'label' => 'Hour-plus workouts',
                'description' => 'Sessions of an hour or more',
                'filter' => [[
                    'type' => 'activity',
                    'conditions' => [
                        ['field' => 'duration', 'operator' => 'gte', 'value' => '3600'],
                    ],
                ]],
            ],
            [
                'key' => 'long-haul',
                'label' => 'Long-haul flights',
                'description' => 'Flights over 1,000 miles',
                'filter' => [[
                    'type' => 'flight',
                    'conditions' => [
                        ['field' => 'distance', 'operator' => 'gte', 'value' => (string) Distance::fromMiles(1000)],
                    ],
                ]],
            ],
            [
                'key' => 'solid-sleeps',
                'label' => 'Solid sleeps',
                'description' => 'Nights of 8 hours or more',
                'filter' => [[
                    'type' => 'sleep',
                    'conditions' => [
                        ['field' => 'duration', 'operator' => 'gte', 'value' => '28800'],
                    ],
                ]],
            ],
            [
                'key' => 'anything-photos',
                'label' => 'Anything with a photo',
                'description' => 'Every entry that has a photo',
                'filter' => [[
                    'type' => 'any',
                    'conditions' => [
                        ['field' => 'photos', 'operator' => 'has_any', 'value' => null],
                    ],
                ]],
            ],
        ];
    }
}
