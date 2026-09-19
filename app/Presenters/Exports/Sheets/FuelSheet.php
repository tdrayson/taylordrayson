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

    public function render(ExportData $data): string
    {
        $lines = [
            Sheet::rule(self::WIDTH, '='),
            ...$this->centredBlock($this->value($data, 'station')),
            ...$this->centredBlock($this->value($data, 'location')),
            Sheet::rule(self::WIDTH, '-'),
            Sheet::leader('Litres', $this->value($data, 'litres'), self::WIDTH),
            Sheet::leader('Price', $this->value($data, 'price_per_litre'), self::WIDTH),
            Sheet::leader('Fuel', $this->value($data, 'cost'), self::WIDTH),
            Sheet::rule(self::WIDTH, '-'),
            Sheet::row('TOTAL', $this->value($data, 'cost'), self::WIDTH),
            '',
            Sheet::row('ODOMETER', $this->value($data, 'odometer'), self::WIDTH),
        ];

        return implode("\n", $lines)."\n";
    }

    /**
     * A value centred, wrapped across as many lines as it needs: a station
     * name or address routinely exceeds the width, and centre() alone clips
     * rather than wraps.
     *
     * @return list<string>
     */
    private function centredBlock(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_map(fn (string $line): string => Sheet::centre($line, self::WIDTH), Sheet::wrap($value, self::WIDTH));
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
