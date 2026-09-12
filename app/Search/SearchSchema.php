<?php

namespace App\Search;

use App\Datasets\Dataset;
use App\Datasets\Datasets;

/**
 * The filterable field catalogue for the advanced search query builder, keyed by
 * timeline type (plus a generic `any` type). Each field carries a category so the
 * builder can present a cascading category → field picker, and drives the backend
 * compiler via its column / dataType / relation. The field specs themselves are
 * declared on each dataset.
 */
class SearchSchema
{
    /**
     * Text columns per type used by the generic "Anything" text search.
     *
     * @return array<string, list<string>>
     */
    public static function textColumns(): array
    {
        return array_filter(
            array_map(fn (Dataset $dataset): array => $dataset->textColumns(), Datasets::all()),
            fn (array $columns): bool => $columns !== [],
        );
    }

    /**
     * Allowed operators for a given data type.
     *
     * @return array<int, string>
     */
    public static function operatorsFor(string $dataType): array
    {
        return match ($dataType) {
            'text' => ['contains', 'not_contains', 'equals', 'starts_with', 'ends_with'],
            'enum' => ['is', 'is_not', 'contains', 'not_contains'],
            'number', 'duration' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'not_between'],
            'media' => ['has_any', 'has_none', 'gte', 'gt', 'lt', 'lte', 'eq', 'neq', 'between'],
            'day' => ['on', 'not_on', 'before', 'after', 'between', 'not_between'],
            'month', 'year' => ['in', 'not_in', 'before', 'after', 'between', 'not_between'],
            default => [],
        };
    }

    /**
     * The full schema: model class + normalised fields for every type, plus a
     * generic `any` type carrying only the shared date and free-text fields.
     *
     * @return array<string, array{label: string, model: ?class-string, fields: array<string, array<string, mixed>>}>
     */
    public static function types(): array
    {
        $schema = [
            'any' => [
                'label' => 'Anything',
                'model' => null,
                'fields' => self::normalise([
                    'text' => ['label' => 'Text', 'dataType' => 'text', 'column' => null, 'category' => 'Where', 'operators' => ['contains']],
                    'photos' => ['label' => 'Media', 'dataType' => 'media', 'column' => null, 'category' => 'Where', 'suffix' => 'photos'],
                ]),
            ],
        ];

        foreach (Datasets::all() as $type => $dataset) {
            $schema[$type] = [
                'label' => $dataset->plural(),
                'model' => $dataset->model(),
                'fields' => self::normalise($dataset->searchFields()),
            ];
        }

        return $schema;
    }

    /**
     * The schema shaped for the front-end builder.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forClient(): array
    {
        return collect(self::types())
            ->map(fn (array $type, string $key): array => [
                'type' => $key,
                'label' => $type['label'],
                'fields' => collect($type['fields'])
                    ->map(fn (array $field): array => [
                        'key' => $field['key'],
                        'label' => $field['label'],
                        'category' => $field['category'],
                        'dataType' => $field['dataType'],
                        'operators' => $field['operators'],
                        'prefix' => $field['prefix'] ?? null,
                        'suffix' => $field['suffix'] ?? null,
                        'measure' => $field['measure'] ?? null,
                        'store' => $field['store'] ?? null,
                        'options' => $field['dataType'] === 'enum' && ! isset($field['relation']) && $type['model'] !== null
                            ? self::options($type['model'], $field['column'])
                            : null,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Distinct stored values for an enum field, to populate the builder's dropdown.
     *
     * @param  class-string  $model
     * @return array<int, string>
     */
    private static function options(string $model, string $column): array
    {
        return $model::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }

    /**
     * Prepend the shared date field and attach the key + operator list to each field.
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, array<string, mixed>>
     */
    private static function normalise(array $fields): array
    {
        $when = [
            'day' => ['label' => 'Day', 'dataType' => 'day', 'column' => 'occurred_at', 'category' => 'When'],
            'month' => ['label' => 'Month', 'dataType' => 'month', 'column' => 'occurred_at', 'category' => 'When'],
            'year' => ['label' => 'Year', 'dataType' => 'year', 'column' => 'occurred_at', 'category' => 'When'],
        ];

        $normalised = $when + $fields;

        foreach ($normalised as $key => $field) {
            $normalised[$key] = [
                ...$field,
                'key' => $key,
                'operators' => $field['operators'] ?? self::operatorsFor($field['dataType']),
            ];
        }

        return $normalised;
    }
}
