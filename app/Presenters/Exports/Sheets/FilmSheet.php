<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;
use App\Data\ExportField;

/**
 * A film printed as a perforated cinema ticket stub. Reads the export only:
 * every string here is a field's display value, so this layout cannot drift
 * from the data. `->raw` is read once, for the ticket number, purely as the
 * seed for the decorative barcode's bar widths.
 */
final class FilmSheet
{
    public const WIDTH = 46;

    private const INNER = self::WIDTH - 2;

    /** Where a paired row's second label starts, so DATE/RATED and YEAR/RUNTIME line up. */
    private const COLUMN = 22;

    /** Left margin for every content row, so text doesn't sit flush against the tear edge. */
    private const MARGIN = '  ';

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::ticket([
                Sheet::centre('CINEMA TICKET * ADMIT ONE *', self::INNER),
                null,
                ...array_map(fn (string $line): string => self::MARGIN.$line, $this->details($data)),
                null,
                Sheet::centre(Sheet::barcode($this->seed($data)), self::INNER),
                self::MARGIN.$this->footer($data),
            ], self::WIDTH),
        ]);
    }

    /**
     * The film title, then its date and rating paired on one row, then its
     * year and runtime paired on the next. A row drops entirely only when
     * both of its fields are absent; either side alone still prints.
     *
     * @return list<string>
     */
    private function details(ExportData $data): array
    {
        return array_values(array_filter([
            $this->labelled('FILM', $data->field('film')),
            $this->pair('DATE', $data->field('date'), 'RATED', $data->field('rating')),
            $this->pair('YEAR', $data->field('year'), 'RUNTIME', $data->field('runtime')),
        ]));
    }

    private function labelled(string $label, ?ExportField $field): ?string
    {
        return $field === null ? null : str_pad($label, 6).': '.$field->display;
    }

    /**
     * Two fields on one row, the second under its own literal label since a
     * ticket abbreviates it ("Rating" the field, "RATED" the row). Either
     * side is dropped when its field is absent; the row drops when both are.
     */
    private function pair(string $leftLabel, ?ExportField $left, string $rightLabel, ?ExportField $right): ?string
    {
        $leftText = $left === null ? '' : str_pad($leftLabel, 6).': '.$left->display;
        $rightText = $right === null ? '' : $rightLabel.': '.$right->display;

        return match (true) {
            $leftText === '' && $rightText === '' => null,
            $rightText === '' => $leftText,
            $leftText === '' => $rightText,
            default => str_pad($leftText, self::COLUMN).$rightText,
        };
    }

    /** The ticket number and the ticket holder, paired on the stub's last row. */
    private function footer(ExportData $data): string
    {
        $number = $data->field('ticket_number');
        $owner = $data->field('owner');

        $left = $number === null ? '' : 'No. '.$number->display;
        $right = $owner === null ? '' : str_replace(' ', '', mb_strtoupper($owner->display));

        return $right === '' ? $left : str_pad($left, self::COLUMN).$right;
    }

    /** The ticket number's raw id, the only `->raw` read: it seeds the barcode's bar widths, not its content. */
    private function seed(ExportData $data): int
    {
        $raw = $data->field('ticket_number')?->raw;

        return is_int($raw) ? $raw : 0;
    }
}
