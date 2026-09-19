<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;
use App\Data\ExportField;

/**
 * A book printed as its library card. Reads the export only: every string
 * here is a field's display value, so this layout cannot drift from the data.
 */
final class BookSheet
{
    private const WIDTH = 46;

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::rule(self::WIDTH, '='),
            ...$this->centredBlock($this->value($data, 'book')),
            Sheet::rule(self::WIDTH, '='),
            ...$this->centredBlock($this->value($data, 'author')),
            '',
            Sheet::rule(self::WIDTH, '-'),
            ...$this->maybeLeader($data, 'Pages', 'pages'),
            ...$this->progress($data),
            ...$this->maybeLeader($data, 'Started', 'started'),
            Sheet::rule(self::WIDTH, '-'),
        ]);
    }

    /**
     * Progress as a dotted leader row plus a bar, sized from raw against the
     * 0-100 scale. Dropped entirely when there is no progress reading.
     *
     * @return list<string>
     */
    private function progress(ExportData $data): array
    {
        $field = $data->field('progress');

        if ($field === null) {
            return [];
        }

        $labelColumn = str_pad('Progress', 10);
        $percentColumn = str_pad($field->display, 8, ' ', STR_PAD_LEFT);
        $barWidth = self::WIDTH - mb_strlen($labelColumn) - mb_strlen($percentColumn) - 2;

        // Geometry (the bar's width) reads raw for precision; the printed percentage still comes from display.
        return [$labelColumn.' '.Sheet::bar((float) $field->raw / 100, $barWidth).' '.$percentColumn];
    }

    /**
     * A dotted label/value row, dropped entirely rather than printed empty
     * when the field carries no value.
     *
     * @return list<string>
     */
    private function maybeLeader(ExportData $data, string $label, string $key): array
    {
        $field = $this->field($data, $key);

        return $field === null ? [] : [Sheet::leader($label, $field->display, self::WIDTH)];
    }

    private function field(ExportData $data, string $key): ?ExportField
    {
        return $data->field($key);
    }

    /**
     * A value centred, wrapped across as many lines as it needs, so a long
     * title or author name is not clipped mid-word.
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
