<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * A This Week With episode printed as its rundown. Reads the export only:
 * every string here is a field's display value, so this layout cannot drift
 * from the data.
 */
final class ThisWeekWithSheet
{
    private const WIDTH = 46;

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::rule(self::WIDTH, '='),
            Sheet::centre('THIS WEEK WITH', self::WIDTH),
            Sheet::centre($this->value($data, 'episode'), self::WIDTH),
            Sheet::rule(self::WIDTH, '='),
            '',
            ...$this->maybeRow($data, 'SEASON', 'season'),
            ...$this->maybeRow($data, 'EPISODE', 'number'),
            ...$this->maybeRow($data, 'DURATION', 'duration'),
            ...$this->published($data),
        ]);
    }

    /**
     * When the episode went out, from the entry's occurred instant rather
     * than a field: that is what publishing one means.
     *
     * @return list<string>
     */
    private function published(ExportData $data): array
    {
        return $data->occurred === null
            ? []
            : [Sheet::row('PUBLISHED', $data->occurred->display, self::WIDTH)];
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

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
