<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;

/**
 * The /now dashboard printed as a status board. Reads the export only:
 * every string here is a field's display value, so this layout cannot
 * drift from the data.
 *
 * Fields are grouped under headings rather than run together, because a
 * reading needs its neighbours to mean anything: "10h 16m" says nothing
 * until it sits under LAST NIGHT.
 */
final class NowSheet
{
    private const WIDTH = 46;

    /**
     * The board's sections, in order, each naming the field keys it prints.
     * A key with no field is skipped and an empty section drops entirely,
     * so a phone that has never reported its battery prints no DEVICE block.
     *
     * @var array<string, list<string>>
     */
    private const SECTIONS = [
        'WHERE' => ['location', 'country', 'timezone'],
        'WEATHER' => ['conditions', 'temperature', 'humidity', 'wind'],
        'ACTIVITY' => ['move', 'exercise', 'stand', 'steps'],
        'SLEEP' => ['slept', 'sleep_score'],
        'READING' => ['reading', 'reading_author'],
        'DEVICE' => ['device', 'battery', 'charging'],
    ];

    /**
     * Keys whose value is free text rather than a reading, so it wraps in
     * its own column instead of being squeezed onto one line.
     *
     * @var list<string>
     */
    private const WRAPPED = ['reading'];

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::rule(self::WIDTH, '='),
            Sheet::centre('NOW', self::WIDTH),
            ...$this->asOf($data),
            Sheet::rule(self::WIDTH, '='),
            ...$this->sections($data),
        ]);
    }

    /**
     * When these readings were last true. Without it the board reads as
     * current however stale it is.
     *
     * @return list<string>
     */
    private function asOf(ExportData $data): array
    {
        return $data->occurred === null ? [] : [Sheet::centre($data->occurred->display, self::WIDTH)];
    }

    /** @return list<string> */
    private function sections(ExportData $data): array
    {
        $lines = [];

        foreach (self::SECTIONS as $heading => $keys) {
            $rows = [];

            foreach ($keys as $key) {
                $field = $data->field($key);

                if ($field === null) {
                    continue;
                }

                $rows[] = in_array($key, self::WRAPPED, true)
                    ? Sheet::wrapped(mb_strtoupper($field->label), $field->display, self::WIDTH)
                    : Sheet::row(mb_strtoupper($field->label), $field->display, self::WIDTH);
            }

            if ($rows !== []) {
                $lines = [...$lines, '', " {$heading}", ...$rows];
            }
        }

        return $lines;
    }
}
