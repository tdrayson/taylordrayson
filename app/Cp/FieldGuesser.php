<?php

namespace App\Cp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FieldGuesser
{
    /** @var array<int, string> Columns whose contents are long-form prose. */
    private const LONG_TEXT = [
        'description', 'long_description', 'notes', 'excerpt',
        'transcript', 'show_notes', 'address', 'reason',
    ];

    /**
     * Derive a field definition for every fillable column on a model.
     *
     * Required versus nullable is derived from the database schema: a column is
     * required when it exists in the schema, is NOT NULL, and has no default
     * value. Columns absent from the schema, nullable, or carrying a default are
     * treated as optional. Booleans are always optional because false is valid.
     *
     * @param  class-string<Model>  $model
     * @return array<string, array{key: string, label: string, type: string, options: array<int, mixed>, rules: array<int, string>, locked: bool, help: ?string}>
     */
    public function guess(string $model): array
    {
        $instance = new $model;
        $casts = $instance->getCasts();
        $table = $instance->getTable();

        /** @var Collection<string, array{name: string, nullable: bool, default: mixed}> $columns */
        $columns = collect(Schema::getColumns($table))->keyBy('name');

        $fields = [];

        foreach ($instance->getFillable() as $column) {
            $type = $this->typeFor($column, $casts[$column] ?? null, $table);
            $meta = $columns->get($column);
            $required = $meta !== null
                && $meta['nullable'] === false
                && $meta['default'] === null
                && $type !== 'boolean';

            $fields[$column] = [
                'key' => $column,
                'label' => Str::headline($column),
                'type' => $type,
                'options' => [],
                'rules' => $this->rulesFor($column, $type, $required),
                'locked' => false,
                'help' => null,
            ];
        }

        return $fields;
    }

    private function typeFor(string $column, ?string $cast, string $table): string
    {
        $schemaType = Schema::hasColumn($table, $column) ? Schema::getColumnType($table, $column) : 'string';

        return match (true) {
            $column === 'occurred_at', $cast === 'datetime', in_array($schemaType, ['datetime', 'timestamp'], true) => 'datetime',
            $schemaType === 'date' => 'date',
            $cast === 'boolean', $schemaType === 'boolean' => 'boolean',
            $cast === 'array', $schemaType === 'json' => 'json',
            $this->isNumeric($cast, $schemaType) => 'number',
            in_array($column, self::LONG_TEXT, true), $schemaType === 'text' => 'textarea',
            default => 'text',
        };
    }

    private function isNumeric(?string $cast, string $schemaType): bool
    {
        if (in_array($cast, ['integer', 'float', 'double'], true) || Str::startsWith((string) $cast, 'decimal')) {
            return true;
        }

        return in_array($schemaType, ['integer', 'bigint', 'smallint', 'decimal', 'float', 'double'], true);
    }

    /**
     * Build validation rules for a single column.
     *
     * @param  bool  $required  Whether the column is NOT NULL with no default.
     * @return array<int, string>
     */
    private function rulesFor(string $column, string $type, bool $required): array
    {
        if ($column === 'occurred_at') {
            return ['required', 'date'];
        }

        if ($type === 'boolean') {
            return ['boolean'];
        }

        $presence = $required ? 'required' : 'nullable';

        return match ($type) {
            'datetime', 'date' => [$presence, 'date'],
            'number' => [$presence, 'numeric'],
            'json' => [$presence, 'json'],
            default => [$presence, 'string'],
        };
    }
}
