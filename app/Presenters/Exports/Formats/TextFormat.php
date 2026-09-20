<?php

namespace App\Presenters\Exports\Formats;

use App\Data\ExportData;
use App\Datasets\Datasets;
use App\Enums\ExportFormat;
use App\Presenters\Exports\NowExport;
use App\Presenters\Exports\PageExport;
use App\Presenters\Exports\Sheets\Sheet;

/**
 * The export printed. A type with a sheet gets its own layout; everything
 * else gets the plain aligned table.
 */
final class TextFormat extends Format
{
    public function format(): ExportFormat
    {
        return ExportFormat::Txt;
    }

    public function render(ExportData $data): string
    {
        return $this->sheetFor($data)?->render($data) ?? $this->table($data);
    }

    /**
     * The type's sheet: Datasets::for() to the type, then a method on its
     * *Export, mirroring the explicit Page carve-out in
     * ExportPresenter::export(). Page and Now carry no Dataset, so they
     * resolve by type string instead; Now has no model at all.
     */
    private function sheetFor(ExportData $data): ?object
    {
        $export = match ($data->typeValue()) {
            'page' => new PageExport,
            'now' => new NowExport,
            default => Datasets::for($data->typeValue())?->export(),
        };

        return $export !== null && method_exists($export, 'sheet') ? $export->sheet() : null;
    }

    private function table(ExportData $data): string
    {
        $lines = [Sheet::heading($data->title)];

        if ($data->occurred !== null) {
            $lines[] = $data->occurred->display;
        }

        $lines[] = '';

        foreach ($data->fields as $field) {
            // A field can print as two lines (label, then a wrapped value)
            // rather than one, so every physical line gets the indent, not
            // just the row's first.
            foreach (explode("\n", Sheet::row($field->label, $field->display, 44)) as $line) {
                $lines[] = '  '.$line;
            }
        }

        return implode("\n", $lines)."\n";
    }
}
