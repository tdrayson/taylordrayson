<?php

namespace App\Support\Health;

use App\Models\Sleep;
use Illuminate\Support\Carbon;

class SleepProcessor implements HealthProcessor
{
    /** @var list<string> */
    private const COLUMNS = ['occurred_at', 'bedtime', 'wake_time', 'duration', 'awake', 'rem', 'core', 'deep', 'source', 'stages'];

    /** @var list<string> */
    private const SCORE_COLUMNS = ['score', 'duration_score', 'bedtime_score', 'interruption_score'];

    /** Nights of recent history used to establish a typical bedtime. */
    private const BASELINE_WINDOW = 14;

    /** Fewest baseline nights before the bedtime component is scored at all. */
    private const BASELINE_MINIMUM = 3;

    public function __construct(private SleepAggregator $aggregator, private SleepScore $scorer) {}

    /**
     * @return list<string>
     */
    public function handles(): array
    {
        return ['sleep_analysis'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload, ?string $csvPath = null): void
    {
        $segments = $this->segmentsFrom($payload);

        if ($segments === []) {
            return;
        }

        $records = $this->aggregator->aggregate($segments);

        foreach ($records as $record) {
            Sleep::query()->updateOrCreate(
                ['occurred_at' => Carbon::parse($record['occurred_at'])->startOfDay()],
                $record,
            );
        }

        $this->mirrorToCsv($records, $csvPath);
        $this->scoreAll($csvPath);
    }

    /**
     * Extract and de-duplicate `sleep_analysis` segments from one payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function segmentsFrom(array $payload): array
    {
        $segments = [];
        $seen = [];

        foreach ($payload['data']['metrics'] ?? [] as $metric) {
            if (($metric['name'] ?? null) !== 'sleep_analysis' || ! is_array($metric['data'] ?? null)) {
                continue;
            }

            foreach ($metric['data'] as $segment) {
                $key = ($segment['source'] ?? '').'|'.($segment['start'] ?? '').'|'.($segment['end'] ?? '').'|'.($segment['value'] ?? '');

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $segments[] = $segment;
            }
        }

        return $segments;
    }

    /**
     * Recompute the sleep score for every row and write it to the CSV and the
     * database. Each night's bedtime component is judged against a trailing
     * median of recent bedtimes, so rows are scored in chronological order.
     *
     * @return int Number of rows scored, or 0 if the CSV does not exist.
     */
    public function scoreAll(?string $csvPath = null): int
    {
        $path = $this->csvPath($csvPath);

        if (! is_file($path)) {
            return 0;
        }

        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
            }
        }

        fclose($handle);

        usort($rows, fn (array $first, array $second): int => strcmp($first['occurred_at'], $second['occurred_at']));

        $headers = array_values(array_unique([...$headers, ...self::SCORE_COLUMNS]));
        $models = Sleep::query()->get()->keyBy(fn (Sleep $sleep): string => $sleep->occurred_at->toDateString());
        $recent = [];

        foreach ($rows as $index => $row) {
            $bedtimeMinutes = $this->bedtimeMinutes($row['bedtime']);
            $baseline = count($recent) >= self::BASELINE_MINIMUM ? $this->median($recent) : null;

            $scores = $this->scorer->score([
                'duration' => (int) $row['duration'],
                'awake' => (int) $row['awake'],
                'rem' => (int) $row['rem'],
                'core' => (int) $row['core'],
                'deep' => (int) $row['deep'],
                'wake_events' => $this->wakeEvents($row['stages'] ?? ''),
                'bedtime_minutes' => $bedtimeMinutes,
                'baseline_minutes' => $baseline,
            ]);

            $rows[$index] = [...$row, ...array_map('strval', $scores)];

            $recent[] = $bedtimeMinutes;
            $recent = array_slice($recent, -self::BASELINE_WINDOW);

            $models->get($row['occurred_at'])?->forceFill($scores)->saveQuietly();
        }

        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $column): string => (string) ($row[$column] ?? ''), $headers));
        }

        fclose($handle);

        return count($rows);
    }

    /** Minutes from 6pm to the bedtime, so evening and pre-dawn times stay ordered. */
    private function bedtimeMinutes(string $bedtime): int
    {
        $time = Carbon::parse($bedtime);

        return (($time->hour * 60 + $time->minute) - 1080 + 1440) % 1440;
    }

    /** Number of distinct awake periods recorded in the night's stages. */
    private function wakeEvents(string $stagesJson): int
    {
        $stages = json_decode($stagesJson, true);

        if (! is_array($stages)) {
            return 0;
        }

        return count(array_filter($stages, fn ($stage): bool => ($stage['stage'] ?? null) === 'awake'));
    }

    /**
     * @param  list<int>  $values
     */
    private function median(array $values): int
    {
        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 0
            ? (int) round(($values[$middle - 1] + $values[$middle]) / 2)
            : $values[$middle];
    }

    /**
     * Merge the records into the CSV by night, preserving every existing row.
     * A no-op if the target CSV does not already exist.
     *
     * @param  array<string, array<string, mixed>>  $records
     */
    public function mirrorToCsv(array $records, ?string $csvPath = null): int
    {
        $path = $this->csvPath($csvPath);

        if (! is_file($path)) {
            return 0;
        }

        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }

            $combined = array_combine($headers, $row);
            $rows[$combined['occurred_at']] = $combined;
        }

        fclose($handle);

        foreach ($records as $night => $record) {
            $rows[$night] = $this->csvRow($record, $headers);
        }

        ksort($rows);

        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $column): string => (string) ($row[$column] ?? ''), $headers));
        }

        fclose($handle);

        return count($records);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  list<string>  $headers
     * @return array<string, string>
     */
    public function csvRow(array $record, array $headers): array
    {
        $row = [];

        foreach ($headers as $header) {
            $value = $record[$header] ?? null;
            $row[$header] = $header === 'stages' ? (string) json_encode($value) : (string) ($value ?? '');
        }

        return $row;
    }

    /**
     * Resolve the sleep CSV path, preferring an explicit override (e.g. from
     * the command's `--csv` option) and falling back to the production file.
     */
    public function csvPath(?string $override = null): string
    {
        return $override ?: base_path('data/sleep.csv');
    }
}
