<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * An event printed as its ticket. Reads the export only: every string here
 * is a field's display value, so this layout cannot drift from the data.
 */
final class EventSheet
{
    private const WIDTH = 46;

    private const INNER = self::WIDTH - 4;

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::box($this->wrappedLines($this->value($data, 'event')), self::WIDTH),
            '',
            ...$this->centredBlock($this->value($data, 'venue')),
            ...$this->centredBlock($this->value($data, 'location')),
            '',
            ...$this->doors($data),
            ...$this->maybeRow($data, 'ENDS', 'ends'),
        ]);
    }

    /**
     * When the doors opened, taken from the entry's occurred instant rather
     * than a field: that is when an event begins.
     *
     * @return list<string>
     */
    private function doors(ExportData $data): array
    {
        if ($data->occurred === null) {
            return [];
        }

        return [Sheet::row('DOORS', $data->occurred->display, self::WIDTH)];
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
     * A value wrapped to the box's inner width and centred within it, so a
     * long event name is not clipped mid-word.
     *
     * @return list<string>
     */
    private function wrappedLines(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_map(fn (string $line): string => Sheet::centre($line, self::INNER), Sheet::wrap($value, self::INNER));
    }

    /**
     * A value centred, wrapped across as many lines as it needs, so a long
     * venue name or address is not clipped mid-word.
     *
     * @return list<string>
     */
    private function centredBlock(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_map(fn (string $line): string => Sheet::centre($line, self::WIDTH), Sheet::wrap($value, self::WIDTH));
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
