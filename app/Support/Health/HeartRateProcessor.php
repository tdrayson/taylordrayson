<?php

namespace App\Support\Health;

use App\Models\Activity;
use App\Support\Downsample;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class HeartRateProcessor implements HealthProcessor
{
    private const MAX_POINTS = 240;

    public function __construct(private HeartRateMatcher $matcher) {}

    /**
     * @return list<string>
     */
    public function handles(): array
    {
        return ['heart_rate'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload, ?string $csvPath = null, bool $overwrite = false, int $cap = self::MAX_POINTS): void
    {
        $samples = $this->samplesFrom($payload);

        if ($samples === []) {
            return;
        }

        /** @var Collection<int, Activity> $activities */
        $activities = Activity::query()->get()->keyBy('id');
        $changed = [];

        foreach ($this->matcher->match($samples, $activities) as $id => $aggregate) {
            $activity = $activities->get($id);

            if ($activity instanceof Activity) {
                $changed[$activity->occurred_at->format('Y-m-d H:i:s')] = $this->apply($activity, $aggregate, $cap, $overwrite);
            }
        }

        if ($changed !== []) {
            $this->mirrorToCsv($changed, $csvPath);
        }
    }

    /**
     * Merge this run's samples into the activity's stored series and persist.
     * The time-series is always rebuilt, since no other source provides it.
     *
     * The average and max are decided independently: each is filled from the
     * samples only where it is currently empty, so a richer source's value
     * (e.g. Strava's average) is preserved while a missing max is still
     * completed from the same data. $overwrite recomputes both regardless. The
     * computed peak defends any prior stored maximum so it is never lowered.
     *
     * @param  array{avg: int, max: int, series: list<array{time: string, bpm: int}>}  $aggregate
     * @return array{average_heart_rate: int|float|null, max_heart_rate: int|float|null, heart_rate: list<array{time: string, bpm: int}>}
     */
    private function apply(Activity $activity, array $aggregate, int $cap, bool $overwrite): array
    {
        // A non-null altitude series means Strava already streamed this activity,
        // which includes its own heart-rate stream. Apple Health's window-matched
        // samples are a lower-fidelity fallback, so they must not clobber it.
        if ($activity->altitude !== null && ! $overwrite) {
            return [
                'average_heart_rate' => $activity->average_heart_rate,
                'max_heart_rate' => $activity->max_heart_rate,
                'heart_rate' => $this->storedSeries($activity),
            ];
        }

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

        $record = [
            'average_heart_rate' => $overwrite || (float) $activity->average_heart_rate <= 0
                ? (int) round(array_sum($merged) / count($merged))
                : $activity->average_heart_rate,
            'max_heart_rate' => $overwrite || (float) $activity->max_heart_rate <= 0
                ? max($aggregate['max'], (int) round((float) $activity->max_heart_rate))
                : $activity->max_heart_rate,
            'heart_rate' => $this->downsample($series, $cap),
        ];

        $activity->forceFill($record)->save();

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
     * Extract and de-duplicate `heart_rate` samples from one payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array{time: int, avg: int, max: int, source: string}>
     */
    private function samplesFrom(array $payload): array
    {
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
     * Write the new heart-rate columns onto the matching activity rows, keyed by
     * occurred_at, preserving every existing row and column. A no-op if the
     * target CSV does not already exist.
     *
     * @param  array<string, array{average_heart_rate: int, max_heart_rate: int, heart_rate: list<array{time: string, bpm: int}>}>  $changed
     */
    private function mirrorToCsv(array $changed, ?string $csvPath = null): int
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

    /**
     * Resolve the activities CSV path, preferring an explicit override (e.g.
     * from the command's `--csv` option) and falling back to the production file.
     */
    private function csvPath(?string $override = null): string
    {
        return $override ?: base_path('data/activities.csv');
    }
}
