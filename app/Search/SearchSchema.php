<?php

namespace App\Search;

use App\Datasets\Dataset;
use App\Datasets\Datasets;
use App\Enums\EntryStatus;
use BackedEnum;

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
                    ...ResponseFields::all(),
                ]),
            ],
        ];

        foreach (Datasets::all() as $type => $dataset) {
            $schema[$type] = [
                'label' => $dataset->plural(),
                'model' => $dataset->model(),
                'fields' => self::normalise([...$dataset->searchFields(), 'status' => self::statusField(), ...ResponseFields::all()]),
            ];
        }

        return $schema;
    }

    /**
     * The one status field every type shares, with fixed options so a guest's
     * builder never learns which statuses exist in the data.
     *
     * @return array<string, mixed>
     */
    private static function statusField(): array
    {
        return [
            'label' => 'Status',
            'dataType' => 'enum',
            'column' => 'status',
            'category' => 'Publishing',
            'enum' => EntryStatus::class,
            'options' => array_map(fn (EntryStatus $status): string => $status->value, EntryStatus::cases()),
        ];
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
                        'options' => self::optionsFor($type, $field),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * An enum field's dropdown options as value/label pairs. The value is the
     * string as stored; the label comes from the field's enum where it has a
     * case for that value, and is otherwise the stored string made readable.
     *
     * @param  array<string, mixed>  $type  The field's owning type.
     * @param  array<string, mixed>  $field  The normalised field definition.
     * @return array<int, array{value: string, label: string}>|null Null for every field that is not a dropdown.
     */
    private static function optionsFor(array $type, array $field): ?array
    {
        $values = $field['options'] ?? null;

        if ($values === null) {
            if ($field['dataType'] !== 'enum' || isset($field['relation']) || $type['model'] === null) {
                return null;
            }

            $values = self::options($type['model'], $field['column']);
        }

        /** @var class-string<BackedEnum>|null $enum */
        $enum = $field['enum'] ?? null;

        return array_map(fn (string $value): array => [
            'value' => $value,
            'label' => ($enum ? $enum::tryFrom($value)?->label() : null) ?? self::readable($value),
        ], $values);
    }

    /**
     * A stored value with no enum case behind it, as a label: separators become
     * spaces and the first letter is capitalised. Values already written for
     * display, such as a place category, come back untouched.
     */
    private static function readable(string $value): string
    {
        return ucfirst(str_replace(['-', '_'], ' ', $value));
    }

    /**
     * Distinct stored values for an enum field, to populate the builder's dropdown.
     * A cast column plucks as enum cases, so they are unwrapped back to what the
     * filter will actually carry.
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
            ->map(fn (mixed $value): string => $value instanceof BackedEnum ? (string) $value->value : (string) $value)
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
