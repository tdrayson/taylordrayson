<?php

namespace App\Presenters\Exports\Formats;

use App\Data\ExportData;
use App\Data\ExportField;
use App\Datasets\Datasets;
use App\Enums\ExportFormat;

/**
 * The entry as the INSERT that made it. Built from the published field list
 * rather than the model, so no column the export withholds can appear here.
 */
final class SqlFormat extends Format
{
    public function format(): ExportFormat
    {
        return ExportFormat::Sql;
    }

    /** A locked entry publishes no fields, so there is no row to write. */
    public function supports(ExportData $data): bool
    {
        return ! $data->locked && $data->fields !== [];
    }

    public function render(ExportData $data, array $trail = []): string
    {
        $table = $this->table($data);
        $columns = array_map(fn (ExportField $field): string => $field->key, $data->fields);
        $values = array_map(fn (ExportField $field): string => $this->value($field), $data->fields);

        $lines = ["-- {$data->url}"];

        foreach ($trail as $extension => $url) {
            $lines[] = "-- {$extension}: {$url}";
        }

        $lines[] = '';
        $lines[] = "-- columns are the export's published fields, not the {$table} table's schema";
        $lines[] = "INSERT INTO {$table} (".implode(', ', $columns).')';
        $lines[] = 'VALUES ('.implode(', ', $values).');';

        return implode("\n", $lines)."\n";
    }

    private function table(ExportData $data): string
    {
        $model = Datasets::for($data->typeValue())?->model();

        return $model === null ? $data->typeValue() : (new $model)->getTable();
    }

    /** A structured `raw` (an airport, a route) is not a plausible column value, so the display string stands in for it. */
    private function value(ExportField $field): string
    {
        return $this->literal(is_array($field->raw) ? $field->display : $field->raw);
    }

    private function literal(mixed $raw): string
    {
        return match (true) {
            $raw === null => 'NULL',
            is_bool($raw) => $raw ? 'TRUE' : 'FALSE',
            is_int($raw), is_float($raw) => (string) $raw,
            default => "'".str_replace("'", "''", (string) $raw)."'",
        };
    }
}
