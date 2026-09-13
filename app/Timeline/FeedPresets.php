<?php

namespace App\Timeline;

use App\Datasets\Dataset;
use App\Datasets\Datasets;
use App\Enums\DatasetKind;

/**
 * Named feed bundles addressable by `?filter=` on any feed URL. `curated` and
 * `everything` are bespoke; the rest are derived one per DatasetKind, so a
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
                'types' => ['note', 'article', 'project', 'film', 'tv-episode', 'book', 'this-week-with', 'appearance'],
            ],
            'everything' => [
                'label' => 'Everything',
                'description' => 'The whole kitchen sink. Every last thing I track.',
                'types' => array_keys(TypeRegistry::all()),
            ],
            ...collect(DatasetKind::cases())
                ->mapWithKeys(fn (DatasetKind $kind): array => [$kind->value => [
                    'label' => $kind->label(),
                    'description' => $kind->description(),
                    'types' => array_keys(array_filter(Datasets::all(), fn (Dataset $dataset): bool => $dataset->kind() === $kind)),
                ]])
                ->filter(fn (array $preset): bool => $preset['types'] !== [])
                ->all(),
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
