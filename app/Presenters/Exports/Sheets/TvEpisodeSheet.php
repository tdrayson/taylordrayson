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

    private const INNER = self::WIDTH - 2;

    /** Where a paired row's second label starts, so SEASON/NUMBER and DATE/RATED line up. */
    private const COLUMN = 22;

    /** Left margin for every content row, so text doesn't sit flush against the tear edge. */
    private const MARGIN = '  ';

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::ticket([
                $this->header($data),
                null,
                ...array_map(fn (string $line): string => self::MARGIN.$line, $this->details($data)),
                null,
                Sheet::centre(Sheet::barcode($this->seed($data)), self::INNER),
                self::MARGIN.$this->footer($data),
            ], self::WIDTH),
        ]);
    }

    /**
     * The show name, centred when it fits the stub's default width. A show
     * name longer than that is left uncentred rather than clipped: the
     * ticket widens around it instead of cutting a title short.
     */
    private function header(ExportData $data): string
    {
        $show = mb_strtoupper($this->value($data, 'show'));

        return mb_strwidth($show) > self::INNER ? $show : Sheet::centre($show, self::INNER);
    }

    /**
     * The episode title, then its season and number paired on one row, then
     * its date and rating paired on the next. A row drops entirely only when
     * both of its fields are absent; either side alone still prints.
     *
     * @return list<string>
     */
    private function details(ExportData $data): array
    {
        return array_values(array_filter([
            $this->labelled('EPISODE', $data->field('episode')),
            $this->pair('SEASON', $data->field('season'), 'NUMBER', $data->field('number')),
            $this->pair('DATE', $data->field('date'), 'RATED', $data->field('rating')),
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

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
