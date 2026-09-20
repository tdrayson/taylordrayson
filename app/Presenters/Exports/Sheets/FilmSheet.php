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

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::ticket([
                ['centre' => 'CINEMA TICKET * ADMIT ONE *'],
                null,
                ...$this->details($data),
                null,
                ['centre' => Sheet::barcode($this->seed($data))],
                $this->footer($data),
            ], self::WIDTH),
        ]);
    }

    /**
     * One field per row, each under the ticket's own literal label since a
     * ticket abbreviates them ("Rating" the field, "RATED" the row). A row
     * drops entirely when its field is absent.
     *
     * @return list<array<string, string>>
     */
    private function details(ExportData $data): array
    {
        // Only nulls drop: a blank spacer row is meaningful here, and a
        // bare array_filter() would discard it as falsy.
        return array_values(array_filter([
            $this->labelled('FILM', $data->field('film')),
            $this->labelled('DATE', $data->field('date')),
            // RELEASE, not YEAR: the DATE row above it is when I watched it.
            $this->labelled('RELEASE', $data->field('year')),
            $this->labelled('RUNTIME', $data->field('runtime')),
            // The rating is mine, not the film's, so it sits below the facts
            // with a gap between: RELEASE and RUNTIME are true of the film
            // whoever is looking at it.
            ...$this->rating($data),
        ], fn (array|string|null $row): bool => $row !== null));
    }

    /**
     * The rating, set apart from the film's own facts.
     *
     * @return list<array<string, string>|string>
     */
    private function rating(ExportData $data): array
    {
        $field = $data->field('rating');

        return $field === null ? [] : ['', ['label' => 'RATED', 'value' => $field->display]];
    }

    /** @return array<string, string>|null */
    private function labelled(string $label, ?ExportField $field): ?array
    {
        return $field === null ? null : ['label' => $label, 'value' => $field->display];
    }

    /**
     * The ticket number and the ticket holder, on the stub's last row.
     *
     * @return array<string, string>
     */
    private function footer(ExportData $data): array
    {
        $number = $data->field('ticket_number');
        $owner = $data->field('owner');

        return [
            'label' => $number === null ? '' : 'No. '.$number->display,
            'value' => $owner === null ? '' : str_replace(' ', '', mb_strtoupper($owner->display)),
        ];
    }

    /** The ticket number's raw id, the only `->raw` read: it seeds the barcode's bar widths, not its content. */
    private function seed(ExportData $data): int
    {
        $raw = $data->field('ticket_number')?->raw;

        return is_int($raw) ? $raw : 0;
    }
}
