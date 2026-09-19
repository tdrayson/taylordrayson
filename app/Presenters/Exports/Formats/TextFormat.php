<?php

namespace App\Presenters\Exports\Formats;

use App\Data\ExportData;
use App\Datasets\Datasets;
use App\Enums\ExportFormat;
use App\Presenters\Exports\Sheets\Sheet;

/**
 * The export printed. A type with a sheet gets its own layout; everything
 * else, and every locked export, gets the plain aligned table.
 */
final class TextFormat extends Format
{
    public function format(): ExportFormat
    {
        return ExportFormat::Txt;
    }

    public function render(ExportData $data): string
    {
        $sheet = $data->locked ? null : $this->sheetFor($data);

        return $sheet?->render($data) ?? $this->table($data);
    }

    /**
     * The type's sheet, resolved the same way a dataset resolves its export
     * presenter: Datasets::for() to the type, then a method on its *Export.
     * A type with no sheet yet, or no dataset at all, falls through to null.
     */
    private function sheetFor(ExportData $data): ?object
    {
        $export = Datasets::for($data->typeValue())?->export();

        return $export !== null && method_exists($export, 'sheet') ? $export->sheet() : null;
    }

    private function table(ExportData $data): string
    {
        $lines = [Sheet::heading($data->title)];

        if ($data->occurred !== null) {
            $lines[] = $data->occurred->display;
        }

        if ($data->locked) {
            return implode("\n", $lines)."\n";
        }

        $lines[] = '';

        foreach ($data->fields as $field) {
            $lines[] = '  '.Sheet::row($field->label, $field->display, 44);
        }

        return implode("\n", $lines)."\n";
    }
}
