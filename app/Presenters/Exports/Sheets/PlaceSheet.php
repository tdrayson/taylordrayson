<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * A check-in printed as a passport stamp. Reads the export only: every
 * string here is a field's display value, so this layout cannot drift
 * from the data.
 */
final class PlaceSheet
{
    private const WIDTH = 46;

    /**
     * What a line may measure and still fit between the arcs. The stamp is
     * widest across its middle, so this is the narrower budget of the rows
     * above and below it, not the full width.
     */
    private const GAP = 32;

    public function render(ExportData $data): string
    {
        return Sheet::join([Sheet::stamp($this->lines($data), self::WIDTH)]);
    }

    /**
     * The venue and address are wrapped rather than passed whole: a full
     * address runs past the stamp's width, and `Sheet::stamp()` drops a
     * line it cannot fit between the arcs instead of overwriting them.
     *
     * @return list<string>
     */
    private function lines(ExportData $data): array
    {
        return [
            '',
            mb_strtoupper($this->value($data, 'category')),
            ...$this->wrapped($this->value($data, 'venue')),
            ...$this->wrapped($this->value($data, 'location')),
            '',
            $this->when($data),
        ];
    }

    /** @return list<string> */
    private function wrapped(string $value): array
    {
        return $value === '' ? [] : Sheet::wrap($value, self::GAP);
    }

    /** When the check-in happened, the thing a stamp exists to record. */
    private function when(ExportData $data): string
    {
        return $data->occurred === null ? '' : mb_strtoupper($data->occurred->display);
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
