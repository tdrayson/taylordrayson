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

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::ticket([
                ['centre' => 'EVENT TICKET * ADMIT ONE *'],
                null,
                ...$this->details($data),
                null,
                ['centre' => Sheet::barcode($this->seed($data))],
                $this->footer($data),
            ], self::WIDTH),
        ]);
    }

    /**
     * The event, then its venue and when doors opened, then when it ends
     * when that's known. Each row drops entirely when its field is absent.
     *
     * @return list<array<string, string>>
     */
    private function details(ExportData $data): array
    {
        return array_values(array_filter([
            $this->labelled('EVENT', $data->field('event')),
            $this->labelled('VENUE', $data->field('venue')),
            $this->start($data),
            $this->labelled('END', $data->field('ends')),
        ]));
    }

    /**
     * When it started, taken from the entry's occurred instant rather than a
     * field. START rather than DOORS: an event here is anything attended,
     * not only the sort of thing that has a foyer.
     *
     * @return array<string, string>|null
     */
    private function start(ExportData $data): ?array
    {
        return $data->occurred === null ? null : ['label' => 'START', 'value' => $data->occurred->display];
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
