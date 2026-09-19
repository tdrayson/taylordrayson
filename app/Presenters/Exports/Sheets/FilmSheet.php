<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;
use App\Data\ExportField;

/**
 * A film printed as its ticket stub. Reads the export only: every string
 * here is a field's display value, so this layout cannot drift from the data.
 */
final class FilmSheet
{
    private const WIDTH = 46;

    private const INNER = self::WIDTH - 4;

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::box($this->titleLines($data), self::WIDTH),
            '',
            ...$this->rating($data),
            ...$this->maybeRow($data, 'YEAR', 'year'),
            ...$this->maybeRow($data, 'RUNTIME', 'runtime'),
        ]);
    }

    /**
     * The film title, wrapped and centred inside the box so a long title is
     * not clipped mid-word.
     *
     * @return list<string>
     */
    private function titleLines(ExportData $data): array
    {
        $title = $this->value($data, 'film');

        if ($title === '') {
            return [];
        }

        return array_map(fn (string $line): string => Sheet::centre($line, self::INNER), Sheet::wrap($title, self::INNER));
    }

    /**
     * The rating as a row of stars, sized from raw against a five-star
     * scale. Dropped entirely when there is no rating.
     *
     * @return list<string>
     */
    private function rating(ExportData $data): array
    {
        $field = $this->field($data, 'rating');

        if ($field === null) {
            return [];
        }

        // Geometry (the star count) reads raw for precision; the printed rating still comes from display.
        return [
            Sheet::centre(Sheet::stars((float) $field->raw / 10).'  '.$field->display, self::WIDTH),
            '',
        ];
    }

    private function field(ExportData $data, string $key): ?ExportField
    {
        return $data->field($key);
    }

    /**
     * A label/value row, dropped entirely rather than printed empty when
     * the field carries no value.
     *
     * @return list<string>
     */
    private function maybeRow(ExportData $data, string $label, string $key): array
    {
        $field = $this->field($data, $key);

        return $field === null ? [] : [Sheet::row($label, $field->display, self::WIDTH)];
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
