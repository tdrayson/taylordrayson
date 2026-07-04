<?php

namespace App\Search;

use App\Timeline\TypeRegistry;

/**
 * The filterable field catalogue for the advanced search query builder, keyed by
 * timeline type (plus a generic `any` type). Each field carries a category so the
 * builder can present a cascading category → field picker, and drives the backend
 * compiler via its column / dataType / relation.
 */
class SearchSchema
{
    /**
     * Field specs per type. Each is [label, dataType, column, category] with an
     * optional 'relation' for fields on a related model (e.g. flight airline).
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    private const FIELDS = [
        'activity' => [
            'name' => ['label' => 'Name', 'dataType' => 'text', 'column' => 'name', 'category' => 'Activity'],
            'kind' => ['label' => 'Type', 'dataType' => 'enum', 'column' => 'type', 'category' => 'Activity'],
            'distance' => ['label' => 'Distance', 'dataType' => 'number', 'column' => 'distance', 'category' => 'Metrics', 'suffix' => 'km', 'unit' => 'km'],
            'duration' => ['label' => 'Duration', 'dataType' => 'duration', 'column' => 'duration', 'category' => 'Metrics'],
            'calories' => ['label' => 'Calories', 'dataType' => 'number', 'column' => 'calories', 'category' => 'Metrics', 'suffix' => 'kcal'],
            'avg_hr' => ['label' => 'Avg heart rate', 'dataType' => 'number', 'column' => 'average_heart_rate', 'category' => 'Metrics', 'suffix' => 'bpm'],
            'max_hr' => ['label' => 'Max heart rate', 'dataType' => 'number', 'column' => 'max_heart_rate', 'category' => 'Metrics', 'suffix' => 'bpm'],
            'photos' => ['label' => 'Photos', 'dataType' => 'media', 'column' => null, 'category' => 'Media', 'suffix' => 'photos'],
        ],
        'sleep' => [
            'duration' => ['label' => 'Duration', 'dataType' => 'duration', 'column' => 'duration', 'category' => 'Sleep'],
            'awake' => ['label' => 'Awake', 'dataType' => 'duration', 'column' => 'awake', 'category' => 'Stages'],
            'rem' => ['label' => 'REM', 'dataType' => 'duration', 'column' => 'rem', 'category' => 'Stages'],
            'core' => ['label' => 'Core', 'dataType' => 'duration', 'column' => 'core', 'category' => 'Stages'],
            'deep' => ['label' => 'Deep', 'dataType' => 'duration', 'column' => 'deep', 'category' => 'Stages'],
            'source' => ['label' => 'Source', 'dataType' => 'enum', 'column' => 'source', 'category' => 'Sleep'],
        ],
        'calorie' => [
            'name' => ['label' => 'Name', 'dataType' => 'text', 'column' => 'name', 'category' => 'Food'],
            'meal' => ['label' => 'Meal', 'dataType' => 'enum', 'column' => 'meal', 'category' => 'Food'],
            'calories' => ['label' => 'Calories', 'dataType' => 'number', 'column' => 'calories', 'category' => 'Macros', 'suffix' => 'kcal'],
            'protein' => ['label' => 'Protein', 'dataType' => 'number', 'column' => 'protein', 'category' => 'Macros', 'suffix' => 'g'],
            'carbs' => ['label' => 'Carbs', 'dataType' => 'number', 'column' => 'carbs', 'category' => 'Macros', 'suffix' => 'g'],
            'fat' => ['label' => 'Fat', 'dataType' => 'number', 'column' => 'fat', 'category' => 'Macros', 'suffix' => 'g'],
            'sugars' => ['label' => 'Sugars', 'dataType' => 'number', 'column' => 'sugars', 'category' => 'Macros', 'suffix' => 'g'],
        ],
        'media' => [
            'title' => ['label' => 'Title', 'dataType' => 'text', 'column' => 'title', 'category' => 'Media'],
            'kind' => ['label' => 'Type', 'dataType' => 'enum', 'column' => 'type', 'category' => 'Media'],
            'rating' => ['label' => 'Rating', 'dataType' => 'number', 'column' => 'rating', 'category' => 'Media'],
        ],
        'event' => [
            'name' => ['label' => 'Name', 'dataType' => 'text', 'column' => 'name', 'category' => 'Event'],
            'kind' => ['label' => 'Type', 'dataType' => 'enum', 'column' => 'type', 'category' => 'Event'],
            'price' => ['label' => 'Ticket price', 'dataType' => 'number', 'column' => 'ticket_price', 'category' => 'Event', 'prefix' => '£'],
            'notes' => ['label' => 'Notes', 'dataType' => 'text', 'column' => 'notes', 'category' => 'Event'],
            'venue' => ['label' => 'Venue', 'dataType' => 'text', 'column' => 'venue_name', 'category' => 'Location'],
            'city' => ['label' => 'City', 'dataType' => 'text', 'column' => 'city', 'category' => 'Location'],
            'country' => ['label' => 'Country', 'dataType' => 'text', 'column' => 'country', 'category' => 'Location'],
            'photos' => ['label' => 'Photos', 'dataType' => 'media', 'column' => null, 'category' => 'Media', 'suffix' => 'photos'],
        ],
        'appearance' => [
            'title' => ['label' => 'Title', 'dataType' => 'text', 'column' => 'title', 'category' => 'Appearance'],
            'show' => ['label' => 'Show', 'dataType' => 'text', 'column' => 'show_name', 'category' => 'Appearance'],
            'kind' => ['label' => 'Type', 'dataType' => 'enum', 'column' => 'type', 'category' => 'Appearance'],
            'description' => ['label' => 'Description', 'dataType' => 'text', 'column' => 'description', 'category' => 'Appearance'],
            'photos' => ['label' => 'Photos', 'dataType' => 'media', 'column' => null, 'category' => 'Media', 'suffix' => 'photos'],
        ],
        'podcast' => [
            'topic' => ['label' => 'Topic', 'dataType' => 'text', 'column' => 'topic', 'category' => 'Episode'],
            'season' => ['label' => 'Season', 'dataType' => 'number', 'column' => 'season_number', 'category' => 'Episode'],
            'episode' => ['label' => 'Episode', 'dataType' => 'number', 'column' => 'episode_number', 'category' => 'Episode'],
            'duration' => ['label' => 'Duration', 'dataType' => 'duration', 'column' => 'duration', 'category' => 'Episode'],
            'notes' => ['label' => 'Show notes', 'dataType' => 'text', 'column' => 'show_notes', 'category' => 'Episode'],
            'transcript' => ['label' => 'Transcript', 'dataType' => 'text', 'column' => 'transcript', 'category' => 'Episode'],
        ],
        'flight' => [
            'airline' => ['label' => 'Airline', 'dataType' => 'text', 'relation' => 'airline', 'column' => 'name', 'category' => 'Flight'],
            'number' => ['label' => 'Flight number', 'dataType' => 'text', 'column' => 'flight_number', 'category' => 'Flight'],
            'cabin' => ['label' => 'Cabin class', 'dataType' => 'enum', 'column' => 'cabin_class', 'category' => 'Flight'],
            'reason' => ['label' => 'Reason', 'dataType' => 'text', 'column' => 'reason', 'category' => 'Flight'],
            'origin' => ['label' => 'Origin (IATA)', 'dataType' => 'text', 'column' => 'origin_iata', 'category' => 'Route'],
            'destination' => ['label' => 'Destination (IATA)', 'dataType' => 'text', 'column' => 'destination_iata', 'category' => 'Route'],
            'distance' => ['label' => 'Distance', 'dataType' => 'number', 'column' => 'distance', 'category' => 'Route', 'suffix' => 'mi', 'unit' => 'mi'],
            'flight_duration' => ['label' => 'Duration', 'dataType' => 'duration', 'column' => 'duration', 'category' => 'Route'],
        ],
        'checkin' => [
            'venue' => ['label' => 'Venue', 'dataType' => 'text', 'column' => 'venue_name', 'category' => 'Place'],
            'category' => ['label' => 'Category', 'dataType' => 'enum', 'column' => 'category', 'category' => 'Place'],
            'description' => ['label' => 'Description', 'dataType' => 'text', 'column' => 'description', 'category' => 'Place'],
            'city' => ['label' => 'City', 'dataType' => 'text', 'column' => 'city', 'category' => 'Location'],
            'county' => ['label' => 'County', 'dataType' => 'text', 'column' => 'county', 'category' => 'Location'],
            'country' => ['label' => 'Country', 'dataType' => 'text', 'column' => 'country', 'category' => 'Location'],
        ],
        'fuel' => [
            'station' => ['label' => 'Station', 'dataType' => 'text', 'column' => 'station', 'category' => 'Fuel'],
            'city' => ['label' => 'City', 'dataType' => 'text', 'column' => 'city', 'category' => 'Fuel'],
            'litres' => ['label' => 'Litres', 'dataType' => 'number', 'column' => 'litres', 'category' => 'Cost', 'suffix' => 'L'],
            'cost' => ['label' => 'Cost', 'dataType' => 'number', 'column' => 'cost', 'category' => 'Cost', 'prefix' => '£'],
            'price' => ['label' => 'Price / litre', 'dataType' => 'number', 'column' => 'price_per_litre', 'category' => 'Cost', 'prefix' => '£'],
            'odometer' => ['label' => 'Odometer', 'dataType' => 'number', 'column' => 'odometer', 'category' => 'Cost', 'suffix' => 'mi'],
        ],
        'project' => [
            'title' => ['label' => 'Title', 'dataType' => 'text', 'column' => 'title', 'category' => 'Project'],
            'status' => ['label' => 'Status', 'dataType' => 'enum', 'column' => 'status', 'category' => 'Project'],
            'description' => ['label' => 'Description', 'dataType' => 'text', 'column' => 'description', 'category' => 'Project'],
            'long_description' => ['label' => 'Long description', 'dataType' => 'text', 'column' => 'long_description', 'category' => 'Project'],
            'photos' => ['label' => 'Photos', 'dataType' => 'media', 'column' => null, 'category' => 'Media', 'suffix' => 'photos'],
        ],
        'article' => [
            'title' => ['label' => 'Title', 'dataType' => 'text', 'column' => 'title', 'category' => 'Article'],
            'excerpt' => ['label' => 'Excerpt', 'dataType' => 'text', 'column' => 'excerpt', 'category' => 'Article'],
            'content' => ['label' => 'Content', 'dataType' => 'text', 'column' => 'content', 'category' => 'Article'],
        ],
        'note' => [
            'content' => ['label' => 'Content', 'dataType' => 'text', 'column' => 'content', 'category' => 'Note'],
        ],
    ];

    /**
     * Text columns per type used by the generic "Anything" text search.
     *
     * @var array<string, array<int, string>>
     */
    public const TEXT_COLUMNS = [
        'activity' => ['name'],
        'calorie' => ['name', 'meal'],
        'media' => ['title'],
        'event' => ['name', 'venue_name', 'city', 'country'],
        'appearance' => ['title', 'show_name', 'description'],
        'podcast' => ['topic', 'show_notes'],
        'flight' => ['flight_number', 'origin_iata', 'destination_iata', 'reason'],
        'checkin' => ['venue_name', 'category', 'city', 'description'],
        'fuel' => ['station', 'city'],
        'project' => ['title', 'description', 'status'],
        'note' => ['content'],
        'article' => ['title', 'excerpt', 'content'],
    ];

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
        $registry = TypeRegistry::all();
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

        foreach (self::FIELDS as $type => $fields) {
            $schema[$type] = [
                'label' => $registry[$type]['label'],
                'model' => $registry[$type]['model'],
                'fields' => self::normalise($fields),
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
