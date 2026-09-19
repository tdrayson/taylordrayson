<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;
use App\Data\ExportField;

/**
 * A flight printed as its wide, three-column boarding pass. Reads the
 * export only: every string here is a field's display value, so this layout
 * cannot drift from the data. `->raw` is read once, for the ticket number,
 * purely as the seed for the decorative barcode's bar widths.
 */
final class FlightSheet
{
    public const WIDTH = 76;

    private const INNER = self::WIDTH - 2;

    /** Floor widths for the grid's three columns; each widens to fit real content. */
    private const COLUMNS = [22, 26, 24];

    /** Where the footer row's "Barcode No." starts. */
    private const FOOTER_COLUMN = 44;

    public function render(ExportData $data): string
    {
        $grid = $this->grid($data);
        $widths = $this->columnWidths($grid);

        return Sheet::join([
            str_repeat('_', self::WIDTH),
            $this->wall(''),
            $this->wall($this->header($data)),
            $this->wall(str_repeat('=', self::INNER)),
            ...array_map(fn (array $row): string => $this->gridLine($row, $widths), $grid),
            $this->wall(str_repeat('-', self::INNER)),
            $this->wall($this->passenger($data)),
            $this->wall(''),
            $this->wall($this->footer($data)),
            '|'.str_repeat('_', self::INNER).'|',
        ]);
    }

    /**
     * The three-column grid: flight/from/date, duration/to/departs,
     * distance/reason/arrives. Every field is optional, so a blank cell
     * (not a bare label) is what a missing one leaves behind.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function grid(ExportData $data): array
    {
        return [
            [
                $this->cell('FLIGHT', $data->field('flight_code')),
                $this->route($data->field('origin_code'), $data->field('origin_city'), 'FROM'),
                $this->cell('DATE', $data->field('date')),
            ],
            [
                $this->cell('DURATION', $data->field('duration')),
                $this->route($data->field('destination_code'), $data->field('destination_city'), 'TO'),
                $this->cell('DEPARTS', $data->field('departs_time')),
            ],
            [
                $this->cell('DISTANCE', $data->field('distance')),
                $this->cell('REASON', $data->field('reason')),
                $this->cell('ARRIVES', $data->field('arrives_time')),
            ],
        ];
    }

    private function cell(string $label, ?ExportField $field): string
    {
        return $field === null ? '' : '  '.$label.': '.$field->display;
    }

    /** An airport's code and city, joined for the FROM/TO cell; either alone still prints. */
    private function route(?ExportField $code, ?ExportField $city, string $label): string
    {
        $parts = array_filter([$code?->display, $city?->display]);

        return $parts === [] ? '' : '  '.$label.': '.implode(' / ', $parts);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $rows
     * @return array{0: int, 1: int, 2: int}
     */
    private function columnWidths(array $rows): array
    {
        $widths = self::COLUMNS;

        foreach ($rows as $row) {
            foreach ($row as $column => $cell) {
                $widths[$column] = max($widths[$column], mb_strwidth($cell));
            }
        }

        return $widths;
    }

    /**
     * @param  array{0: string, 1: string, 2: string}  $row
     * @param  array{0: int, 1: int, 2: int}  $widths
     */
    private function gridLine(array $row, array $widths): string
    {
        $cells = [];

        foreach ($row as $column => $cell) {
            $cells[] = $this->pad($cell, $widths[$column]);
        }

        return '|'.implode('|', $cells).'|';
    }

    /** "BOARDING PASS", the cabin class, and the airline, e.g. a real pass's top line. */
    private function header(ExportData $data): string
    {
        $cabin = mb_strtoupper($this->value($data, 'cabin'));
        $airline = $this->value($data, 'airline');

        $left = $cabin === '' ? '  BOARDING PASS' : $this->pad('  BOARDING PASS', 28).$cabin;

        return $airline === '' ? $left : $this->pad($left, self::INNER - mb_strwidth($airline) - 2).$airline;
    }

    private function passenger(ExportData $data): string
    {
        $field = $data->field('passenger');

        return $field === null ? '' : '  PASSENGER: '.$field->display;
    }

    /** The barcode, then the ticket number under its own literal label. */
    private function footer(ExportData $data): string
    {
        $barcode = '  '.Sheet::barcode($this->seed($data));
        $number = $data->field('ticket_number');

        return $number === null ? $barcode : $this->pad($barcode, self::FOOTER_COLUMN).'Barcode No. '.$number->display;
    }

    private function wall(string $content): string
    {
        return '|'.$this->pad($content, self::INNER).'|';
    }

    private function pad(string $text, int $width): string
    {
        return $text.str_repeat(' ', max(0, $width - mb_strwidth($text)));
    }

    /** The ticket number's raw id, the only `->raw` read: it seeds the barcode's bar widths, not its content. */
    private function seed(ExportData $data): int
    {
        $raw = $data->field('ticket_number')?->raw;

        return is_int($raw) ? $raw : 0;
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
