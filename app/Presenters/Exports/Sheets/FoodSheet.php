<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * A food day printed as its nutrition panel. Reads the export only: every
 * string here is a field's label or display value, so this layout cannot
 * drift from the data.
 */
final class FoodSheet
{
    private const WIDTH = 46;

    private const INNER = self::WIDTH - 4;

    /** The macro fields, in the order their leader rows print. */
    private const MACROS = ['protein', 'carbs', 'fat', 'saturated_fat', 'sugars', 'fibre', 'sodium'];

    public function render(ExportData $data): string
    {
        return Sheet::box([
            'NUTRITION FACTS',
            '',
            Sheet::centre($this->value($data, 'calories'), self::INNER),
            Sheet::rule(self::INNER, '-'),
            ...array_map(fn (string $key): string => $this->macro($data, $key), self::MACROS),
        ], self::WIDTH)."\n";
    }

    private function macro(ExportData $data, string $key): string
    {
        $field = $data->field($key);

        return $field === null ? '' : Sheet::leader($field->label, $field->display, self::INNER);
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
