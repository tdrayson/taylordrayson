<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * A flight printed as its boarding pass. Reads the export only: every string
 * here is a field's display value, so this layout cannot drift from the data.
 */
final class FlightSheet
{
    private const WIDTH = 46;

    public function render(ExportData $data): string
    {
        $lines = [
            Sheet::rule(self::WIDTH, '='),
            Sheet::centre($this->value($data, 'flight'), self::WIDTH),
            Sheet::rule(self::WIDTH, '='),
            '',
            ...$this->route($data),
            '',
            Sheet::row('DEPARTS', $this->value($data, 'departed'), self::WIDTH),
            Sheet::row('ARRIVES', $this->value($data, 'arrived'), self::WIDTH),
            '',
            Sheet::rule(self::WIDTH, '-'),
            Sheet::row('DURATION', $this->value($data, 'duration'), self::WIDTH),
            Sheet::row('DISTANCE', $this->value($data, 'distance'), self::WIDTH),
            Sheet::row('CABIN', $this->value($data, 'cabin'), self::WIDTH),
            Sheet::rule(self::WIDTH, '-'),
        ];

        return implode("\n", $lines)."\n";
    }

    /**
     * Origin and destination centred as their own block rather than paired
     * in one row: full airport names routinely exceed the width between
     * them, and a paired row silently clips the losing side.
     *
     * @return list<string>
     */
    private function route(ExportData $data): array
    {
        return [
            ...array_map(fn (string $line): string => Sheet::centre($line, self::WIDTH), Sheet::wrap($this->value($data, 'origin'), self::WIDTH)),
            Sheet::centre('to', self::WIDTH),
            ...array_map(fn (string $line): string => Sheet::centre($line, self::WIDTH), Sheet::wrap($this->value($data, 'destination'), self::WIDTH)),
        ];
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
