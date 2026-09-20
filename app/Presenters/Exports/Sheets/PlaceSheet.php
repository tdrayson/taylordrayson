<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * A check-in printed as the divided back of a postcard: the place written
 * on the left, a stamp in the top corner and the address on the right.
 * Reads the export only, so this layout cannot drift from the data.
 *
 * Wider than the 46 other sheets settle at, because two columns of address
 * cannot share 46 columns without wrapping every line of both.
 */
final class PlaceSheet
{
    private const WIDTH = 64;

    private const INNER = self::WIDTH - 2;

    /** The message side, left of the divider. */
    private const LEFT = self::INNER / 2;

    /** The address side, right of it, one column narrower for the divider. */
    private const RIGHT = self::INNER - self::LEFT - 1;

    private const MARGIN = '  ';

    private const STAMP = ['+-------+', '| *   * |', '|   *   |', '+-------+'];

    public function render(ExportData $data): string
    {
        $border = '+'.str_repeat('-', self::INNER).'+';
        $right = $this->address($data);
        $left = $this->message($data, count($right));
        $rows = max(count($left), count($right));

        $lines = [$border];

        for ($i = 0; $i < $rows; $i++) {
            $lines[] = '|'
                .$this->cell($left[$i] ?? '', self::LEFT)
                .'|'
                .$this->cell($right[$i] ?? '', self::RIGHT)
                .'|';
        }

        $lines[] = $border;

        return Sheet::join($lines);
    }

    /**
     * The place and what kind of place it is, with the date on the card's
     * last row the way a postcard is signed off. Padded out to whichever
     * side runs longer, so the sign-off sits at the foot rather than
     * directly under the heading.
     *
     * @return list<string>
     */
    private function message(ExportData $data, int $facing): array
    {
        $head = [
            '',
            ...$this->wrap($this->value($data, 'venue'), self::LEFT),
            ...$this->wrap($this->value($data, 'category'), self::LEFT),
        ];

        if ($data->occurred === null) {
            return $head;
        }

        $rows = max(count($head) + 1, $facing);

        return [...$head, ...array_fill(0, $rows - count($head) - 1, ''), mb_strtoupper($data->occurred->display)];
    }

    /**
     * The stamp in the corner, then the address beneath it.
     *
     * @return list<string>
     */
    private function address(ExportData $data): array
    {
        $stamp = array_map(
            fn (string $line): string => str_repeat(' ', max(0, self::RIGHT - mb_strwidth($line) - 2)).$line,
            self::STAMP,
        );

        return [...$stamp, '', ...$this->addressBlock($data)];
    }

    /** @return list<string> */
    private function addressBlock(ExportData $data): array
    {
        return $this->wrap($this->value($data, 'location'), self::RIGHT);
    }

    /**
     * A value wrapped to a column, allowing for the margin either side.
     *
     * @return list<string>
     */
    private function wrap(string $value, int $column): array
    {
        return $value === '' ? [] : Sheet::wrap($value, $column - 4);
    }

    /** One cell of a row, indented and padded to its column's width. */
    private function cell(string $text, int $width): string
    {
        $body = $text === '' ? '' : self::MARGIN.$text;

        // A stamp line arrives pre-indented, so only pad what needs it.
        if ($text !== '' && str_starts_with($text, ' ')) {
            $body = $text;
        }

        return $body.str_repeat(' ', max(0, $width - mb_strwidth($body)));
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
