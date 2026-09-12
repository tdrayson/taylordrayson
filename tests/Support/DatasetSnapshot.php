<?php

namespace Tests\Support;

use App\Data\TypeMeta;
use App\Presenters\CardPresenter;
use App\Search\SearchSchema;
use App\Support\TypeCatalogue;
use App\Timeline\TypeRegistry;

/**
 * Everything the datasets refactor must leave unchanged, as plain arrays.
 * Temporary: deleted once the refactor lands.
 */
final class DatasetSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        return [
            'registry' => array_map(fn (array $definition): array => [
                'slug' => $definition['slug'],
                'model' => $definition['model'],
                'label' => $definition['label'],
                'noun' => $definition['noun'],
                'stats' => $definition['stats'],
                'taxonomy' => $definition['taxonomy'] === null ? null : [
                    'base' => $definition['taxonomy']['base'],
                    'param' => $definition['taxonomy']['param'],
                    'label' => $definition['taxonomy']['label'],
                    'allLabel' => $definition['taxonomy']['allLabel'] ?? null,
                    'title' => isset($definition['taxonomy']['title']) ? ($definition['taxonomy']['title'])('Sample') : null,
                ],
            ], TypeRegistry::all()),
            'catalogue' => array_map(fn (TypeMeta $meta): array => [
                'key' => $meta->key,
                'eyebrow' => $meta->eyebrow(),
                'row' => array_diff_key($meta->toArray(), array_flip(['noun', 'nounPlural', 'kind'])),
            ], TypeCatalogue::timeline()),
            'search' => SearchSchema::types(),
            // Sorted: the constant lists note before article, datasets follow enum order,
            // and the order only affects the sequence of OR clauses, not results.
            'textColumns' => self::sorted(method_exists(SearchSchema::class, 'textColumns')
                ? SearchSchema::textColumns()
                : SearchSchema::TEXT_COLUMNS),
            'cards' => array_map(
                fn (array $definition): string => CardPresenter::card(new $definition['model'])::class,
                TypeRegistry::all(),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function sorted(array $values): array
    {
        ksort($values);

        return $values;
    }
}
