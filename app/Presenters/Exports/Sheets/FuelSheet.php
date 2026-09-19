<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * A fuel fill-up printed as its till receipt. Reads the export only: every
 * string here is a field's display value, so this layout cannot drift from
 * the data.
 */
final class FuelSheet
{
    private const WIDTH = 46;

    private const INNER = self::WIDTH - 4;

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::box([
                '',
                ...$this->centredBlock($this->value($data, 'station')),
                '',
                ...$this->centredBlock($this->value($data, 'location')),
                '',
                ...$this->item($data),
                '',
                ...$this->maybeRow($data, 'TOTAL', 'cost'),
                '',
                ...$this->maybeRow($data, 'ODOMETER', 'odometer'),
            ], self::WIDTH),
        ]);
    }

    /**
     * The fill as one item line: the quantity, at its unit price when known,
     * left, the amount actually charged, right. Dropped when there is
     * neither a quantity nor a cost to show.
     *
     * @return list<string>
     */
    private function item(ExportData $data): array
    {
        $litres = $data->field('litres');
        $cost = $data->field('cost');

        if ($litres === null || $cost === null) {
            return [];
        }

        $price = $data->field('price_per_litre');
        $label = $price === null ? $litres->display : "{$litres->display} @ {$price->display}";

        return [Sheet::row($label, $cost->display, self::INNER)];
    }

    /**
     * A label/value row, dropped entirely rather than printed empty when
     * the field carries no value.
     *
     * @return list<string>
     */
    private function maybeRow(ExportData $data, string $label, string $key): array
    {
        $field = $data->field($key);

        return $field === null ? [] : [Sheet::row($label, $field->display, self::INNER)];
    }

    /**
     * A value wrapped to the width, each line centred, so a long station
     * name or address is not clipped mid-word.
     *
     * @return list<string>
     */
    private function centredBlock(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_map(fn (string $line): string => Sheet::centre($line, self::INNER), Sheet::wrap($value, self::INNER));
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
