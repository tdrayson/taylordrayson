<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * A fuel fill-up printed as its forecourt receipt. Reads the export only:
 * every string here is a field's display value, so this layout cannot drift
 * from the data. `->raw` is read once, for the receipt number, purely as the
 * seed for the decorative barcode's bar widths.
 */
final class FuelSheet
{
    private const WIDTH = 46;

    private const CONTENT = self::WIDTH - 1;

    public function render(ExportData $data): string
    {
        return Sheet::join([
            ...$this->header($data),
            $this->transaction($data),
            Sheet::rule(self::WIDTH, '-'),
            ...$this->fuelType($data),
            '',
            ...$this->fuelLines($data),
            Sheet::rule(self::WIDTH, '-'),
            ...$this->maybeFilled($data, 'TOTAL FUEL', 'cost'),
            Sheet::rule(self::WIDTH, '-'),
            ...$this->maybeLabelled($data, 'ODOMETER', 'odometer'),
            '',
            Sheet::centre('THANK YOU FOR YOUR FUEL', self::WIDTH),
            Sheet::centre('DRIVE SAFELY ALWAYS!', self::WIDTH),
            Sheet::centre(Sheet::barcode($this->seed($data)), self::WIDTH),
            Sheet::rule(self::WIDTH, '='),
        ]);
    }

    /**
     * The rule-framed vendor block: the station and locality when either is
     * known, or a bare "FUEL" so the receipt still announces itself rather
     * than framing an empty gap. Most fills carry no station at all, so this
     * fallback is the common case, not the rare one.
     *
     * @return list<string>
     */
    private function header(ExportData $data): array
    {
        $brand = $this->value($data, 'brand');

        $vendor = [
            // The brand heads the receipt the way a forecourt's sign does,
            // with the individual station and its locality beneath.
            ...$this->centredBlock($brand === '' ? '' : mb_strtoupper($brand).' GARAGE'),
            ...$this->centredBlock($this->value($data, 'station')),
            ...$this->centredBlock($this->value($data, 'locality')),
        ];

        return [
            Sheet::rule(self::WIDTH, '='),
            ...($vendor === [] ? [Sheet::centre('FUEL', self::WIDTH)] : $vendor),
            Sheet::rule(self::WIDTH, '='),
        ];
    }

    /** The date/time left, the receipt number right, both already field displays. */
    private function transaction(ExportData $data): string
    {
        return ' '.Sheet::row($this->value($data, 'receipt_time'), 'Ref. '.$this->value($data, 'receipt_ref'), self::CONTENT);
    }

    /**
     * The fill as two receipt rows, volume then price, dropped together when
     * there is no quantity or cost to show, and the price row dropped alone
     * when the fill carries no unit price.
     *
     * @return list<string>
     */
    private function fuelLines(ExportData $data): array
    {
        $litres = $data->field('litres');
        $cost = $data->field('cost');

        if ($litres === null || $cost === null) {
            return [];
        }

        $price = $data->field('price_per_litre');

        return array_values(array_filter([
            ' '.Sheet::row('VOLUME', $litres->display, self::CONTENT),
            $price === null ? null : ' '.Sheet::row('PRICE', $price->display, self::CONTENT),
        ]));
    }

    /**
     * A "LABEL: value" line, dropped entirely when the field carries no
     * value.
     *
     * @return list<string>
     */
    private function maybeLabelled(ExportData $data, string $label, string $key): array
    {
        $field = $data->field($key);

        return $field === null ? [] : [' '.$label.': '.$field->display];
    }

    /**
     * The fuel type row, its value in receipt caps: "Petrol" prints as
     * "PETROL" the way a pump receipt would, though the field itself stays
     * title case for every other format.
     *
     * @return list<string>
     */
    private function fuelType(ExportData $data): array
    {
        $field = $data->field('fuel_type');

        return $field === null ? [] : [' FUEL TYPE: '.mb_strtoupper($field->display)];
    }

    /**
     * A label/value row filled to the width, dropped entirely when the field
     * carries no value.
     *
     * @return list<string>
     */
    private function maybeFilled(ExportData $data, string $label, string $key): array
    {
        $field = $data->field($key);

        return $field === null ? [] : [' '.Sheet::row($label, $field->display, self::CONTENT)];
    }

    /**
     * A value wrapped to the width, each line centred and in receipt caps, so
     * a long station name is not clipped mid-word.
     *
     * @return list<string>
     */
    private function centredBlock(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_map(
            fn (string $line): string => Sheet::centre(mb_strtoupper($line), self::WIDTH),
            Sheet::wrap($value, self::WIDTH),
        );
    }

    /** The receipt number's raw id, the only `->raw` read: it seeds the barcode's bar widths, not its content. */
    private function seed(ExportData $data): int
    {
        $raw = $data->field('receipt_ref')?->raw;

        return is_int($raw) ? $raw : 0;
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
