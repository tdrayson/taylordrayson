<?php

namespace App\Console\Commands\Fetch;

use App\Models\Activity;
use App\Support\Downsample;
use App\Support\Health\HeartRateMatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Signature('health:heart_rate {--all : Process every captured payload, not just the latest} {--file= : Process a specific raw JSON payload path} {--csv= : Target CSV to mirror into (defaults to data/activities.csv)} {--max-points=240 : Cap the stored per-activity series, downsampling evenly when exceeded} {--overwrite : Recompute the average and max even where a source already set them} {--dry : Report what would change without writing}')]
#[Description('Attach heart-rate to activities from captured Health Auto Export heart_rate data, windowing samples into each activity and merging across batched payloads (database and data/activities.csv)')]
class ImportHealthHeartRate extends Command
{
    public function handle(HeartRateMatcher $matcher): int
    {
        $paths = $this->payloadPaths();

        if ($paths === []) {
            $this->components->warn('No captured payloads to process.');

            return self::SUCCESS;
        }

        /** @var Collection<int, Activity> $activities */
        $activities = Activity::query()->get()->keyBy('id');
        $cap = max(0, (int) $this->option('max-points'));
        $changed = [];
        $sampleCount = 0;

        // Decode one capture at a time so memory stays flat no matter how many
        // months of captures exist; the per-activity merge in apply() stitches
        // samples that span more than one file together.
        foreach ($paths as $path) {
            $samples = $this->samplesFrom($path);
            $sampleCount += count($samples);

            if ($samples === []) {
                continue;
            }

            foreach ($matcher->match($samples, $activities) as $id => $aggregate) {
                $activity = $activities->get($id);

                if ($activity instanceof Activity) {
                    $changed[$activity->occurred_at->format('Y-m-d H:i:s')] = $this->apply($activity, $aggregate, $cap);
                }
            }
        }

        if ($sampleCount === 0) {
            $this->components->warn('No heart_rate data found in the captured payloads.');

            return self::SUCCESS;
        }

        if ($changed === []) {
            $this->components->warn(sprintf('Matched none of %s sample(s) to an activity window.', number_format($sampleCount)));

            return self::SUCCESS;
        }

        if ($this->option('dry')) {
            $this->components->info(sprintf('Dry run: %d activity(ies) would gain heart-rate from %s sample(s).', count($changed), number_format($sampleCount)));

            return self::SUCCESS;
        }

        $mirrored = $this->mirrorToCsv($changed);

        $this->components->info(sprintf('Heart-rate attached to %d activity(ies) from %s sample(s). Mirrored %d row(s) into %s.', count($changed), number_format($sampleCount), $mirrored, $this->csvPath()));

        return self::SUCCESS;
    }

    /**
     * Merge this run's samples into the activity's stored series and persist.
     * The time-series is always rebuilt, since no other source provides it.
     *
     * The average and max are decided independently: each is filled from the
     * samples only where it is currently empty, so a richer source's value
     * (e.g. Strava's average) is preserved while a missing max is still
     * completed from the same data. --overwrite recomputes both regardless. The
     * computed peak defends any prior stored maximum so it is never lowered.
     *
     * @param  array{avg: int, max: int, series: list<array{time: string, bpm: int}>}  $aggregate
     * @return array{average_heart_rate: int|float|null, max_heart_rate: int|float|null, heart_rate: list<array{time: string, bpm: int}>}
     */
    private function apply(Activity $activity, array $aggregate, int $cap): array
    {
        $merged = [];

        foreach ([...$this->storedSeries($activity), ...$aggregate['series']] as $point) {
            if (isset($point['time'])) {
                $merged[$point['time']] = (int) ($point['bpm'] ?? 0);
            }
        }

        ksort($merged);

        $series = [];

        foreach ($merged as $time => $bpm) {
            $series[] = ['time' => $time, 'bpm' => $bpm];
        }

        $overwrite = (bool) $this->option('overwrite');

        $record = [
            'average_heart_rate' => $overwrite || (float) $activity->average_heart_rate <= 0
                ? (int) round(array_sum($merged) / count($merged))
                : $activity->average_heart_rate,
            'max_heart_rate' => $overwrite || (float) $activity->max_heart_rate <= 0
                ? max($aggregate['max'], (int) round((float) $activity->max_heart_rate))
                : $activity->max_heart_rate,
            'heart_rate' => $this->downsample($series, $cap),
        ];

        if (! $this->option('dry')) {
            $activity->forceFill($record)->save();
        }

        return $record;
    }

    /**
     * @return list<array{time: string, bpm: int}>
     */
    private function storedSeries(Activity $activity): array
    {
        return is_array($activity->heart_rate) ? array_values($activity->heart_rate) : [];
    }

    /**
     * Reduce a series to at most the cap by keeping evenly spaced points, always
     * retaining the first and last. A cap of zero keeps every point.
     *
     * @param  list<array{time: string, bpm: int}>  $series
     * @return list<array{time: string, bpm: int}>
     */
    private function downsample(array $series, int $cap): array
    {
        return array_map(
            fn (int $index): mixed => $series[$index],
            Downsample::indices(count($series), $cap),
        );
    }

    /**
     * Extract and de-duplicate the heart_rate samples from a single payload.
     *
     * @return array<int, array{time: int, avg: int, max: int, source: string}>
     */
    private function samplesFrom(string $path): array
    {
        $payload = $this->loadPayload($path);

        if ($payload === null) {
            return [];
        }

        $samples = [];

        foreach ($payload['data']['metrics'] ?? [] as $metric) {
            if (($metric['name'] ?? null) !== 'heart_rate' || ! is_array($metric['data'] ?? null)) {
                continue;
            }

            foreach ($metric['data'] as $point) {
                $stamp = $point['start'] ?? $point['date'] ?? null;
                $average = $point['Avg'] ?? $point['avg'] ?? null;

                if (! is_string($stamp) || ! is_numeric($average)) {
                    continue;
                }

                $source = trim((string) ($point['source'] ?? 'unknown')) ?: 'unknown';
                $time = Carbon::parse($stamp)->getTimestamp();

                $samples[$source.'|'.$time] = [
                    'time' => $time,
                    'avg' => (int) round((float) $average),
                    'max' => (int) round((float) ($point['Max'] ?? $average)),
                    'source' => $source,
                ];
            }
        }

        return array_values($samples);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadPayload(string $path): ?array
    {
        $json = $this->option('file') === $path
            ? (string) file_get_contents($path)
            : (string) Storage::disk('local')->get($path);

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * The payload paths to process, newest last.
     *
     * @return list<string>
     */
    private function payloadPaths(): array
    {
        if ($file = $this->option('file')) {
            return [$file];
        }

        $paths = collect(Storage::disk('local')->files('health/incoming'))
            ->filter(fn (string $path): bool => str_ends_with($path, '.json') && ! str_ends_with($path, '.summary.json'))
            ->sort()
            ->values();

        if (! $this->option('all')) {
            $paths = $paths->take(-1);
        }

        return $paths->all();
    }

    /**
     * Write the new heart-rate columns onto the matching activity rows, keyed by
     * occurred_at, preserving every existing row and column.
     *
     * @param  array<string, array{average_heart_rate: int, max_heart_rate: int, heart_rate: list<array{time: string, bpm: int}>}>  $changed
     */
    private function mirrorToCsv(array $changed): int
    {
        $path = $this->csvPath();

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

        $applied = 0;

        foreach ($rows as $index => $row) {
            $record = $changed[$row['occurred_at']] ?? null;

            if ($record === null) {
                continue;
            }

            $rows[$index]['average_heart_rate'] = (string) $record['average_heart_rate'];
            $rows[$index]['max_heart_rate'] = (string) $record['max_heart_rate'];
            $rows[$index]['heart_rate'] = (string) json_encode($record['heart_rate']);
            $applied++;
        }

        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $column): string => (string) ($row[$column] ?? ''), $headers));
        }

        fclose($handle);

        return $applied;
    }

    private function csvPath(): string
    {
        return $this->option('csv') ?: base_path('data/activities.csv');
    }
}
