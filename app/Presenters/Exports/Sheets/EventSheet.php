<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;
use App\Data\ExportField;

/**
 * An event printed as a perforated ticket stub. Reads the export only:
 * every string here is a field's display value, so this layout cannot drift
 * from the data. `->raw` is read once, for the ticket number, purely as the
 * seed for the decorative barcode's bar widths.
 */
final class EventSheet
{
    public const WIDTH = 46;

    private const INNER = self::WIDTH - 2;

    /** Left margin for every content row, so text doesn't sit flush against the tear edge. */
    private const MARGIN = '  ';

    /** Where the footer row's ticket holder starts. */
    private const COLUMN = 22;

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::ticket([
                Sheet::centre('EVENT TICKET * ADMIT ONE *', self::INNER),
                null,
                ...array_map(fn (string $line): string => self::MARGIN.$line, $this->details($data)),
                null,
                Sheet::centre(Sheet::barcode($this->seed($data)), self::INNER),
                self::MARGIN.$this->footer($data),
            ], self::WIDTH),
        ]);
    }

    /**
     * The event, then its venue and when doors opened, then when it ends
     * when that's known. Each row drops entirely when its field is absent.
     *
     * @return list<string>
     */
    private function details(ExportData $data): array
    {
        return array_values(array_filter([
            $this->labelled('EVENT', $data->field('event')),
            $this->labelled('VENUE', $data->field('venue')),
            $this->doors($data),
            $this->labelled('ENDS', $data->field('ends')),
        ]));
    }

    /**
     * When doors opened, taken from the entry's occurred instant rather than
     * a field: that is when an event begins.
     */
    private function doors(ExportData $data): ?string
    {
        return $data->occurred === null ? null : str_pad('DOORS', 6).': '.$data->occurred->display;
    }

    private function labelled(string $label, ?ExportField $field): ?string
    {
        return $field === null ? null : str_pad($label, 6).': '.$field->display;
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
