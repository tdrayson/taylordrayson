<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * A night's sleep printed as its hypnogram. Reads the export only: every
 * string here is a field's display value, so this layout cannot drift from
 * the data.
 */
final class SleepSheet
{
    private const WIDTH = 46;

    /** Each stage's key, its share field and its bar label, in the order the bars print. */
    private const STAGES = [
        ['deep', 'deep_share', 'DEEP'],
        ['core', 'core_share', 'CORE'],
        ['rem', 'rem_share', 'REM'],
        ['awake', 'awake_share', 'AWAKE'],
    ];

    public function render(ExportData $data): string
    {
        $lines = [
            Sheet::rule(self::WIDTH, '='),
            ...$this->centredBlock($data->title),
            Sheet::rule(self::WIDTH, '='),
            '',
            ...array_values(array_filter(array_map(
                fn (array $stage): ?string => $this->stageBar($data, ...$stage),
                self::STAGES,
            ))),
            '',
            Sheet::row('SCORE', $this->value($data, 'score'), self::WIDTH),
        ];

        return Sheet::join($lines);
    }

    /** A stage's proportion of the night, drawn as a label, a bar and its percentage. */
    private function stageBar(ExportData $data, string $stageKey, string $shareKey, string $label): ?string
    {
        $share = $data->field($shareKey);

        if ($this->value($data, $stageKey) === '' || $share === null) {
            return null;
        }

        $labelColumn = str_pad($label, 6);
        $percentColumn = str_pad($share->display, 4, ' ', STR_PAD_LEFT);
        $barWidth = self::WIDTH - mb_strlen($labelColumn) - mb_strlen($percentColumn) - 2;

        return $labelColumn.' '.Sheet::bar(Sheet::fraction($share->display), $barWidth).' '.$percentColumn;
    }

    /**
     * A value centred, wrapped across as many lines as it needs.
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
