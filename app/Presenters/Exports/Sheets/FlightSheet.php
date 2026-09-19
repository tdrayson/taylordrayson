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

        return implode("\n", array_map('rtrim', $lines))."\n";
    }

    /**
     * The two IATA codes, the biggest thing on a real boarding pass, with
     * each city underneath. A sheet may only read display strings, so the
     * export publishes the bare code and city as their own fields rather
     * than this splitting them back out of the combined "name (code)" one.
     *
     * @return list<string>
     */
    private function route(ExportData $data): array
    {
        $originCode = $this->value($data, 'origin_code');
        $destinationCode = $this->value($data, 'destination_code');
        $originCity = $this->value($data, 'origin_city');
        $destinationCity = $this->value($data, 'destination_city');

        $lines = [Sheet::centre(trim("{$originCode}  ->  {$destinationCode}"), self::WIDTH)];

        if ($originCity !== '' && $destinationCity !== '') {
            $lines[] = Sheet::row($originCity, $destinationCity, self::WIDTH);
        }

        return $lines;
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
