<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;
use App\Data\ExportField;

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
            ...$this->maybeRow($data, 'DISTANCE', 'distance'),
            ...$this->maybeRow($data, 'DURATION', 'duration'),
            ...$this->maybeRow($data, 'PACE', 'pace'),
            ...$this->maybeRow($data, 'CALORIES', 'calories'),
            ...$this->heartRate($data),
        ];

        return Sheet::join($lines);
    }

    /**
     * Average heart rate against max, as a row plus a bar. Dropped entirely
     * when either reading is missing.
     *
     * @return list<string>
     */
    private function heartRate(ExportData $data): array
    {
        $effort = $data->field('heart_rate_effort');

        if ($effort === null) {
            return [];
        }

        // Geometry (the bar's width) reads raw for precision; the printed percentage still comes from display.
        return [
            '',
            Sheet::row('HEART RATE', $this->value($data, 'average_heart_rate').' of '.$this->value($data, 'max_heart_rate'), self::WIDTH),
            $this->effortBar($effort),
        ];
    }

    /**
     * The effort bar, labelled and laid out like a sleep stage: an unlabelled
     * "80%" under a heart rate reads as a share of something unstated.
     */
    private function effortBar(ExportField $effort): string
    {
        $labelColumn = str_pad('EFFORT', 6);
        $percentColumn = str_pad($effort->display, 4, ' ', STR_PAD_LEFT);
        $barWidth = self::WIDTH - mb_strwidth($labelColumn) - mb_strwidth($percentColumn) - 2;

        return $labelColumn.' '.Sheet::bar((float) $effort->raw, $barWidth).' '.$percentColumn;
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

        return $field === null ? [] : [Sheet::row($label, $field->display, self::WIDTH)];
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
