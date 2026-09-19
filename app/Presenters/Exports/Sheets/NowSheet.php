<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;
use App\Data\ExportField;

/**
 * The /now dashboard printed as its status board. Reads the export only:
 * every string here is a field's label and display value, so this layout
 * cannot drift from the data.
 */
final class NowSheet
{
    private const WIDTH = 46;

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::box(['NOW'], self::WIDTH),
            '',
            ...array_map(
                fn (ExportField $field): string => Sheet::row($field->label, $field->display, self::WIDTH),
                $data->fields,
            ),
        ]);
    }
}
