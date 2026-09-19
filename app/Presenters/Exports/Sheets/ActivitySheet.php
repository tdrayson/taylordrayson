<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * An activity printed as its stats card. Reads the export only: every string
 * here is a field's display value, so this layout cannot drift from the data.
 */
final class ActivitySheet
{
    private const WIDTH = 46;

    public function render(ExportData $data): string
    {
        $lines = [
            Sheet::rule(self::WIDTH, '='),
            ...$this->centredBlock($this->value($data, 'name') ?: $this->value($data, 'activity')),
            Sheet::rule(self::WIDTH, '='),
            '',
            Sheet::row('DISTANCE', $this->value($data, 'distance'), self::WIDTH),
            Sheet::row('DURATION', $this->value($data, 'duration'), self::WIDTH),
            Sheet::row('PACE', $this->value($data, 'pace'), self::WIDTH),
            Sheet::row('CALORIES', $this->value($data, 'calories'), self::WIDTH),
            ...$this->heartRate($data),
        ];

        return Sheet::join($lines);
    }

    /**
     * Average heart rate against max, as a labelled row plus a bar. Dropped
     * entirely when either reading is missing, rather than drawing an empty
     * bar with nothing behind it.
     *
     * @return list<string>
     */
    private function heartRate(ExportData $data): array
    {
        $effort = $data->field('heart_rate_effort');

        if ($effort === null) {
            return [];
        }

        return [
            '',
            Sheet::row('HEART RATE', $this->value($data, 'average_heart_rate').' of '.$this->value($data, 'max_heart_rate'), self::WIDTH),
            Sheet::centre(Sheet::bar(Sheet::fraction($effort->display)).' '.$effort->display, self::WIDTH),
        ];
    }

    /**
     * A value centred, wrapped across as many lines as it needs, so a long
     * activity name is not clipped mid-word.
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
