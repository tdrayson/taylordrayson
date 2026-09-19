<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * A This Week With episode printed as its rundown. Reads the export only:
 * every string here is a field's display value, so this layout cannot drift
 * from the data. Listen and watch are not printed: a real episode URL is far
 * longer than 46 characters and would only mangle mid-word; the .md and .json
 * formats carry those links intact.
 */
final class ThisWeekWithSheet
{
    private const WIDTH = 46;

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::rule(self::WIDTH, '='),
            Sheet::centre($this->value($data, 'episode'), self::WIDTH),
            Sheet::rule(self::WIDTH, '='),
            '',
            ...$this->wrappedBlock($this->value($data, 'topic')),
            '',
            ...$this->maybeRow($data, 'DURATION', 'duration'),
        ]);
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
     * The topic wrapped to the sheet width, so a long rundown reads as a
     * column rather than one very long line.
     *
     * @return list<string>
     */
    private function wrappedBlock(string $value): array
    {
        return $value === '' ? [] : Sheet::wrap($value, self::WIDTH);
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
