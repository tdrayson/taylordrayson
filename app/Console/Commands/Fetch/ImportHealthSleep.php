<?php

namespace App\Console\Commands\Fetch;

use App\Enums\Source;
use App\Models\Sleep;
use App\Support\Health\SleepAggregator;
use App\Support\Health\SleepScore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Signature('health:sleep {--all : Process every captured payload, not just the latest} {--file= : Process a specific raw JSON payload path} {--csv= : Target CSV to mirror into (defaults to data/sleep.csv)} {--resplit : Re-derive existing rows from their stored stages with the current session logic instead of importing} {--redate : Re-date every existing row to the Apple Health sleep-day (6pm-6pm, dated to wake morning) and resolve resulting collisions} {--score : Recompute the Apple-style sleep score for every row}')]
#[Description('Build nightly sleep records from captured Health Auto Export sleep_analysis data, upserting the database and data/sleep.csv (Oura preferred, Apple Watch fallback)')]
class ImportHealthSleep extends Command
{
    /** @var list<string> */
    private const COLUMNS = ['occurred_at', 'bedtime', 'wake_time', 'duration', 'awake', 'rem', 'core', 'deep', 'source', 'stages'];

    /** @var list<string> */
    private const SCORE_COLUMNS = ['score', 'duration_score', 'bedtime_score', 'interruption_score'];

    /** Nights of recent history used to establish a typical bedtime. */
    private const BASELINE_WINDOW = 14;

    /** Fewest baseline nights before the bedtime component is scored at all. */
    private const BASELINE_MINIMUM = 3;

    public function handle(SleepAggregator $aggregator, SleepScore $scorer): int
    {
        if ($this->option('resplit')) {
            return $this->resplit($aggregator);
        }

        if ($this->option('redate')) {
            return $this->redate($aggregator);
        }

        if ($this->option('score')) {
            return $this->score($scorer);
        }

        $segments = $this->collectSegments();

        if ($segments === []) {
            $this->components->warn('No sleep_analysis data found in the captured payloads.');

            return self::SUCCESS;
        }

        $records = $aggregator->aggregate($segments);

        foreach ($records as $record) {
            Sleep::query()->updateOrCreate(
                ['occurred_at' => Carbon::parse($record['occurred_at'])->startOfDay()],
                $record,
            );
        }

        $mirrored = $this->mirrorToCsv($records);
        $this->score($scorer);

        $this->components->info(sprintf('Imported %d night(s) of sleep. Mirrored %d row(s) into %s.', count($records), $mirrored, $this->csvPath()));

        return self::SUCCESS;
    }

    /**
     * Recompute the sleep score for every row and write it to the CSV and the
     * database. Each night's bedtime component is judged against a trailing
     * median of recent bedtimes, so rows are scored in chronological order.
     */
    private function score(SleepScore $scorer): int
    {
        $path = $this->csvPath();

        if (! is_file($path)) {
            $this->components->error("CSV not found: {$path}");

            return self::FAILURE;
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

            $scores = $scorer->score([
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

        $this->components->info(sprintf('Scored %d night(s) in %s.', count($rows), basename($path)));

        return self::SUCCESS;
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
     * Re-derive every existing row from its stored stages with the current
     * session logic, fixing rows whose duration was inflated by an old merge.
     * Rows with no stored stages, and rows that re-derive identically, are left
     * untouched. Updates both the CSV and the database.
     */
    private function resplit(SleepAggregator $aggregator): int
    {
        $path = $this->csvPath();

        if (! is_file($path)) {
            $this->components->error("CSV not found: {$path}");

            return self::FAILURE;
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

        $changed = 0;

        foreach ($rows as $index => $row) {
            $stored = json_decode($row['stages'] ?? '', true);

            if (! is_array($stored) || $stored === []) {
                continue;
            }

            $record = $aggregator->resplitRow($row['occurred_at'], $row['source'], $stored);

            if (! $this->changedMeaningfully($record, $row)) {
                continue;
            }

            $rows[$index] = [...$row, ...$this->csvRow($record, $headers)];
            $changed++;

            Sleep::query()->updateOrCreate(
                ['occurred_at' => Carbon::parse($record['occurred_at'])->startOfDay()],
                $record,
            );
        }

        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $column): string => (string) ($row[$column] ?? ''), $headers));
        }

        fclose($handle);

        $this->components->info("Re-split {$changed} row(s) in ".basename($path).'.');

        return self::SUCCESS;
    }

    /**
     * Re-date every existing row to the Apple Health sleep-day. Each row's new
     * date is derived from its bedtime; where two rows land on the same day (an
     * overnight sleep plus a daytime nap) the longest is kept. The CSV and the
     * database (and its timeline entries, via model events) are moved together.
     */
    private function redate(SleepAggregator $aggregator): int
    {
        $path = $this->csvPath();

        if (! is_file($path)) {
            $this->components->error("CSV not found: {$path}");

            return self::FAILURE;
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

        $groups = [];

        foreach ($rows as $row) {
            $newDate = $row['bedtime'] !== '' ? $aggregator->dayFor($row['bedtime']) : $row['occurred_at'];
            $groups[$newDate][] = $row;
        }

        $winners = [];      // new date => winning row (occurred_at rewritten)
        $oldDateOf = [];    // new date => the winning row's original date
        $loserDates = [];

        foreach ($groups as $newDate => $candidates) {
            usort($candidates, fn (array $first, array $second): int => ($this->sourceRank($second['source']) <=> $this->sourceRank($first['source']))
                ?: ((int) $second['duration'] <=> (int) $first['duration']));

            $primary = $candidates[0];
            $oldDateOf[$newDate] = $primary['occurred_at'];
            $primary['occurred_at'] = $newDate;
            $winners[$newDate] = $primary;

            foreach (array_slice($candidates, 1) as $loser) {
                $loserDates[] = $loser['occurred_at'];
            }
        }

        ksort($winners);

        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);

        foreach ($winners as $row) {
            fputcsv($handle, array_map(fn (string $column): string => (string) ($row[$column] ?? ''), $headers));
        }

        fclose($handle);

        // Move the database rows (and their timeline entries, via model events).
        $models = Sleep::query()->get()->keyBy(fn (Sleep $sleep): string => $sleep->occurred_at->toDateString());

        foreach ($loserDates as $oldDate) {
            $models->get($oldDate)?->delete();
        }

        $moved = 0;

        foreach ($winners as $newDate => $row) {
            $model = $models[$oldDateOf[$newDate]] ?? null;

            if ($model && $model->occurred_at->toDateString() !== $newDate) {
                $model->occurred_at = Carbon::parse($newDate)->startOfDay();
                $model->save();
                $moved++;
            }
        }

        $this->components->info(sprintf('Re-dated to Apple sleep-days: %d row(s) moved, %d nap collision(s) dropped.', $moved, count($loserDates)));

        return self::SUCCESS;
    }

    private function sourceRank(string $source): int
    {
        return match ($source) {
            Source::Oura->value => 2,
            Source::AppleWatch->value => 1,
            default => 0,
        };
    }

    /**
     * Whether a re-derived record differs from the stored row by more than a
     * couple of minutes, so trivial second-level recomputations don't churn
     * the seed file and bury the real merge fixes.
     *
     * @param  array<string, mixed>  $record
     * @param  array<string, string>  $row
     */
    private function changedMeaningfully(array $record, array $row): bool
    {
        $tolerance = 120;

        $durationDelta = abs((int) $record['duration'] - (int) $row['duration']);
        $bedtimeDelta = abs(strtotime((string) $record['bedtime']) - (int) strtotime($row['bedtime'] ?: 'now'));
        $wakeDelta = abs(strtotime((string) $record['wake_time']) - (int) strtotime($row['wake_time'] ?: 'now'));

        return $durationDelta > $tolerance || $bedtimeDelta > $tolerance || $wakeDelta > $tolerance;
    }

    /**
     * Gather and de-duplicate sleep_analysis segments from the selected payloads.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectSegments(): array
    {
        $segments = [];
        $seen = [];

        foreach ($this->payloads() as $payload) {
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
        }

        return $segments;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function payloads(): array
    {
        if ($file = $this->option('file')) {
            $decoded = json_decode((string) file_get_contents($file), true);

            return is_array($decoded) ? [$decoded] : [];
        }

        $paths = collect(Storage::disk('local')->files('health/incoming'))
            ->filter(fn (string $path): bool => str_ends_with($path, '.json') && ! str_ends_with($path, '.summary.json'))
            ->sort()
            ->values();

        if (! $this->option('all')) {
            $paths = $paths->take(-1);
        }

        return $paths
            ->map(fn (string $path) => json_decode((string) Storage::disk('local')->get($path), true))
            ->filter(fn ($payload): bool => is_array($payload))
            ->values()
            ->all();
    }

    /**
     * Merge the records into the CSV by night, preserving every existing row.
     *
     * @param  array<string, array<string, mixed>>  $records
     */
    private function mirrorToCsv(array $records): int
    {
        $path = $this->csvPath();

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
    private function csvRow(array $record, array $headers): array
    {
        $row = [];

        foreach ($headers as $header) {
            $value = $record[$header] ?? null;
            $row[$header] = $header === 'stages' ? (string) json_encode($value) : (string) ($value ?? '');
        }

        return $row;
    }

    private function csvPath(): string
    {
        return $this->option('csv') ?: base_path('data/sleep.csv');
    }
}
