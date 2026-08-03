<?php

namespace App\Console\Commands\Fetch;

use App\Enums\Source;
use App\Models\Sleep;
use App\Support\Health\SleepAggregator;
use App\Support\Health\SleepProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('health:sleep {--file= : Process a specific raw JSON payload path} {--csv= : Target CSV to mirror into (defaults to data/sleep.csv)} {--resplit : Re-derive existing rows from their stored stages with the current session logic instead of importing} {--redate : Re-date every existing row to the Apple Health sleep-day (6pm-6pm, dated to wake morning) and resolve resulting collisions} {--score : Recompute the Apple-style sleep score for every row}')]
#[Description('Process a captured Health Auto Export sleep_analysis payload, or run maintenance (resplit/redate/score) over the existing sleep data')]
class ImportHealthSleep extends Command
{
    public function handle(SleepProcessor $processor, SleepAggregator $aggregator): int
    {
        if ($this->option('resplit')) {
            return $this->resplit($aggregator, $processor);
        }

        if ($this->option('redate')) {
            return $this->redate($aggregator, $processor);
        }

        if ($this->option('score')) {
            $processor->scoreAll($this->option('csv') ?: null);

            return self::SUCCESS;
        }

        $file = $this->option('file');

        if (! is_string($file) || $file === '') {
            $this->components->info('Sleep now ingests from POST /api/v1/health-export. Use --file to reprocess a payload, or --resplit/--redate/--score for maintenance.');

            return self::SUCCESS;
        }

        $payload = json_decode((string) file_get_contents($file), true);

        if (! is_array($payload)) {
            $this->components->error("Not a JSON payload: {$file}");

            return self::FAILURE;
        }

        $processor->process($payload, $this->option('csv') ?: null);
        $this->components->info('Processed sleep payload.');

        return self::SUCCESS;
    }

    /**
     * Re-derive every existing row from its stored stages with the current
     * session logic, fixing rows whose duration was inflated by an old merge.
     * Rows with no stored stages, and rows that re-derive identically, are left
     * untouched. Updates both the CSV and the database.
     */
    private function resplit(SleepAggregator $aggregator, SleepProcessor $processor): int
    {
        $path = $processor->csvPath($this->option('csv') ?: null);

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

            $rows[$index] = [...$row, ...$processor->csvRow($record, $headers)];
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
    private function redate(SleepAggregator $aggregator, SleepProcessor $processor): int
    {
        $path = $processor->csvPath($this->option('csv') ?: null);

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
}
