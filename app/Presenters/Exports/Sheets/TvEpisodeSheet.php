<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;
use App\Data\ExportField;

/**
 * A TV episode printed as a perforated ticket stub, headed by the show
 * rather than a generic "ticket" banner: there's no venue to name, so the
 * show is the header's context and the episode is the ticket's subject.
 * There's no "ADMIT ONE" line either, since an episode watched at home was
 * never admission to anything. Reads the export only: every string here is
 * a field's display value, so this layout cannot drift from the data.
 * `->raw` is read once, for the ticket number, purely as the seed for the
 * decorative barcode's bar widths.
 */
final class TvEpisodeSheet
{
    public const WIDTH = 46;

    /** Where a paired row's second label starts, so SEASON/NUMBER and DATE/RATED line up. */
    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::ticket([
                $this->header($data),
                null,
                ...$this->details($data),
                null,
                ['centre' => Sheet::barcode($this->seed($data))],
                $this->footer($data),
            ], self::WIDTH),
        ]);
    }

    /**
     * The show name. A name wider than the stub's default widens the ticket
     * rather than being clipped.
     *
     * @return array<string, string>
     */
    private function header(ExportData $data): array
    {
        return ['centre' => mb_strtoupper($this->value($data, 'show'))];
    }

    /**
     * The episode title, then its season and number paired on one row, then
     * its date and rating paired on the next. A row drops entirely only when
     * both of its fields are absent; either side alone still prints.
     *
     * @return list<array<string, string>>
     */
    private function details(ExportData $data): array
    {
        return array_values(array_filter([
            $this->labelled('EPISODE', $data->field('episode')),
            $this->labelled('SEASON', $data->field('season')),
            $this->labelled('NUMBER', $data->field('number')),
            $this->labelled('DATE', $data->field('date')),
            $this->labelled('RATED', $data->field('rating')),
        ]));
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

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
