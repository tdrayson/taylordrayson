<?php

namespace App\Cp;

use Illuminate\Database\Eloquent\Model;
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
     * @param  class-string<Model>  $model
     * @return array<string, array{key: string, label: string, type: string, options: array<int, mixed>, rules: array<int, string>, locked: bool, help: ?string}>
     */
    public function guess(string $model): array
    {
        $instance = new $model;
        $casts = $instance->getCasts();
        $table = $instance->getTable();

        $fields = [];

        foreach ($instance->getFillable() as $column) {
            $type = $this->typeFor($column, $casts[$column] ?? null, $table);

            $fields[$column] = [
                'key' => $column,
                'label' => Str::headline($column),
                'type' => $type,
                'options' => [],
                'rules' => $this->rulesFor($column, $type),
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
     * @return array<int, string>
     */
    private function rulesFor(string $column, string $type): array
    {
        if ($column === 'occurred_at') {
            return ['required', 'date'];
        }

        return match ($type) {
            'datetime', 'date' => ['nullable', 'date'],
            'boolean' => ['boolean'],
            'number' => ['nullable', 'numeric'],
            'json' => ['nullable', 'json'],
            default => ['nullable', 'string'],
        };
    }
}
