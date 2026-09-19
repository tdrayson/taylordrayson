<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * A place check-in printed as its passport stamp. Reads the export only:
 * every string here is a field's display value, so this layout cannot drift
 * from the data.
 */
final class PlaceSheet
{
    private const WIDTH = 46;

    private const INNER = self::WIDTH - 4;

    public function render(ExportData $data): string
    {
        $lines = [
            Sheet::box([
                ...$this->wrappedLines($this->value($data, 'venue'), self::INNER),
                '',
                Sheet::centre($this->value($data, 'category'), self::INNER),
            ], self::WIDTH),
            '',
            ...$this->wrappedLines($this->value($data, 'location'), self::WIDTH),
        ];

        return implode("\n", $lines)."\n";
    }

    /**
     * A value wrapped to the given width and centred within it, so a long
     * venue name or address is not clipped mid-word.
     *
     * @return list<string>
     */
    private function wrappedLines(string $value, int $width): array
    {
        if ($value === '') {
            return [];
        }

        return array_map(fn (string $line): string => Sheet::centre($line, $width), Sheet::wrap($value, $width));
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
