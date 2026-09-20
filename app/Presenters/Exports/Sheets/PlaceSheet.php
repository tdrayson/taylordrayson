<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * A check-in printed as the back of a postcard: the place written top left,
 * a stamp in the corner, the address below it and the date franked across
 * the bottom. Reads the export only, so this layout cannot drift from the
 * data.
 */
final class PlaceSheet
{
    private const WIDTH = 46;

    private const INNER = self::WIDTH - 2;

    /** The card's left and right margins, inside the border. */
    private const MARGIN = '  ';

    /** The stamp, whose height sets how many lines the heading block gets. */
    private const STAMP = ['+-------+', '| *   * |', '|   *   |', '| *   * |', '+-------+'];

    public function render(ExportData $data): string
    {
        $border = '+'.str_repeat('-', self::INNER).'+';

        return Sheet::join([
            $border,
            ...$this->heading($data),
            $this->line(),
            ...$this->address($data),
            $this->line(),
            $this->line(self::MARGIN.str_repeat('-', self::INNER - 4)),
            ...$this->franked($data),
            $border,
        ]);
    }

    /**
     * The place and what kind of place it is, set against the stamp. The
     * stamp is five lines tall and the text starts on its second, which is
     * what keeps the two blocks optically level.
     *
     * @return list<string>
     */
    private function heading(ExportData $data): array
    {
        $left = ['', $this->value($data, 'venue'), $this->value($data, 'category'), '', ''];

        return array_map(
            function (string $text, string $stamp): string {
                $body = self::MARGIN.$text;
                $gap = self::INNER - mb_strwidth($body) - mb_strwidth($stamp) - mb_strwidth(self::MARGIN);

                return '|'.$body.str_repeat(' ', max(1, $gap)).$stamp.self::MARGIN.'|';
            },
            $left,
            self::STAMP,
        );
    }

    /**
     * The address, wrapped so a long one runs down the card the way a
     * written address does rather than off the edge.
     *
     * @return list<string>
     */
    private function address(ExportData $data): array
    {
        $location = $this->value($data, 'location');

        if ($location === '') {
            return [];
        }

        return array_map(
            fn (string $line): string => $this->line(self::MARGIN.$line),
            Sheet::wrap($location, self::INNER - 4),
        );
    }

    /**
     * The date, set right along the bottom like a franking mark.
     *
     * @return list<string>
     */
    private function franked(ExportData $data): array
    {
        if ($data->occurred === null) {
            return [];
        }

        $stamp = mb_strtoupper($data->occurred->display);

        return [$this->line(str_repeat(' ', max(0, self::INNER - mb_strwidth($stamp) - 2)).$stamp.self::MARGIN)];
    }

    /** One ruled line of the card, padded to its inner width. */
    private function line(string $body = ''): string
    {
        return '|'.$body.str_repeat(' ', max(0, self::INNER - mb_strwidth($body))).'|';
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
