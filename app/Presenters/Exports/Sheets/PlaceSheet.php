<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * A check-in printed the way the book and sleep sheets are: a ruled heading
 * and a column of readings, no enclosing frame. Reads the export only, so
 * this layout cannot drift from the data.
 */
final class PlaceSheet
{
    private const WIDTH = 46;

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::rule(self::WIDTH, '='),
            ...$this->centredBlock($this->value($data, 'venue')),
            Sheet::rule(self::WIDTH, '='),
            ...$this->centredBlock($this->value($data, 'category')),
            '',
            Sheet::rule(self::WIDTH, '-'),
            ...$this->maybeRow($data, 'WHERE', 'location'),
            ...$this->maybeRow($data, 'FOR', 'event'),
            ...$this->checkedIn($data),
            Sheet::rule(self::WIDTH, '-'),
        ]);
    }

    /**
     * When the check-in happened, from the entry's occurred instant rather
     * than a field: arriving somewhere is the whole of what a check-in is.
     *
     * @return list<string>
     */
    private function checkedIn(ExportData $data): array
    {
        return $data->occurred === null
            ? []
            : [Sheet::row('CHECKED IN', $data->occurred->display, self::WIDTH)];
    }

    /**
     * A label/value row, dropped entirely rather than printed empty when
     * the field carries no value.
     *
     * @return list<string>
     */
    private function maybeRow(ExportData $data, string $label, string $key): array
    {
        $field = $data->field($key);

        return $field === null ? [] : [Sheet::row($label, $field->display, self::WIDTH)];
    }

    /**
     * A value centred, wrapped across as many lines as it needs, so a long
     * venue name is not clipped mid-word.
     *
     * @return list<string>
     */
    private function centredBlock(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_map(
            fn (string $line): string => Sheet::centre($line, self::WIDTH),
            Sheet::wrap($value, self::WIDTH),
        );
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
