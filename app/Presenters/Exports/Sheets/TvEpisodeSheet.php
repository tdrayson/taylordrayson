<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * A TV episode printed as its ticket stub. Reads the export only: every
 * string here is a field's display value, so this layout cannot drift from
 * the data.
 */
final class TvEpisodeSheet
{
    private const WIDTH = 46;

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::rule(self::WIDTH, '='),
            ...$this->centredBlock($this->value($data, 'show')),
            Sheet::rule(self::WIDTH, '='),
            '',
            ...$this->centredBlock($this->value($data, 'episode')),
            '',
            ...$this->maybeRow($data, 'SEASON', 'season'),
            ...$this->maybeRow($data, 'NUMBER', 'number'),
            ...$this->maybeRow($data, 'RATING', 'rating'),
        ]);
    }

    /**
     * A value centred, wrapped across as many lines as it needs, so a long
     * show or episode title is not clipped mid-word.
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
