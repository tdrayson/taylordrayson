<?php

namespace App\Timeline;

use App\Datasets\Dataset;
use App\Datasets\Datasets;

/**
 * Drives the per-type archive pages and their taxonomy sub-routes, keyed by the
 * card `type` string. A view over App\Datasets\Datasets; taxonomy factories live in Taxonomies.
 */
class TypeRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return array_map(fn (Dataset $dataset): array => [
            'slug' => $dataset->slug(),
            'model' => $dataset->model(),
            'label' => $dataset->plural(),
            'noun' => $dataset->noun(),
            'stats' => $dataset->stats(),
            'taxonomy' => ($factory = $dataset->taxonomy()) !== null ? $factory($dataset->model(), $dataset->slug()) : null,
        ], Datasets::all());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }
}
