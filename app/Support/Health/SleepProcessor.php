<?php

namespace App\Support\Health;

use App\Models\Sleep;
use Illuminate\Support\Carbon;

class SleepProcessor implements HealthProcessor
{
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
    public function process(array $payload): void
    {
        $segments = $this->segmentsFrom($payload);

        if ($segments === []) {
            return;
        }

        $records = $this->aggregator->aggregate($segments);

        foreach ($records as $record) {
            $this->store($record);
        }

        $this->scoreAll();
    }

    /**
     * Save one aggregated sleep, replacing whichever stored sleep covers the
     * same period.
     *
     * Matched on overlap rather than on the day it belongs to. A day key let a
     * nap and a night on the same date overwrite each other, which four days
     * here do; matching on the period keeps them apart while still letting a
     * better source replace a night it genuinely re-reports.
     *
     * @param  array<string, mixed>  $record
     */
    private function store(array $record): void
    {
        $bedtime = $record['bedtime'];
        $wakeTime = $record['wake_time'] ?? $record['occurred_at'];

        $existing = Sleep::query()
            ->where('bedtime', '<', $wakeTime)
            ->where('wake_time', '>', $bedtime)
            ->first();

        // Stored at the moment it ended, so ordering and the card read from the
        // row rather than from an accessor over it.
        $attributes = [...$record, 'occurred_at' => $wakeTime];

        $existing ? $existing->forceFill($attributes)->save() : Sleep::query()->create($attributes);
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
     * Score every night, oldest first.
     *
     * Order matters: the bedtime component is judged against a rolling median
     * of recent bedtimes, so a night can only be scored once the ones before it
     * have contributed to the baseline.
     *
     * @return int Number of nights scored.
     */
    public function scoreAll(): int
    {
        $nights = Sleep::query()->orderBy('occurred_at')->get();
        $recent = [];

        foreach ($nights as $night) {
            $bedtimeMinutes = $this->bedtimeMinutes((string) $night->bedtime);
            $baseline = count($recent) >= self::BASELINE_MINIMUM ? $this->median($recent) : null;

            $scores = $this->scorer->score([
                'duration' => (int) $night->duration,
                'awake' => (int) $night->awake,
                'rem' => (int) $night->rem,
                'core' => (int) $night->core,
                'deep' => (int) $night->deep,
                'bedtime_minutes' => $bedtimeMinutes,
                'baseline_minutes' => $baseline,
            ]);

            $recent[] = $bedtimeMinutes;
            $recent = array_slice($recent, -self::BASELINE_WINDOW);

            // saveQuietly: scoring is derived from what is already stored, so it
            // must not look like an edit and rebuild the timeline entry.
            $night->forceFill($scores)->saveQuietly();
        }

        return $nights->count();
    }

    /** Minutes from 6pm to the bedtime, so evening and pre-dawn times stay ordered. */
    private function bedtimeMinutes(string $bedtime): int
    {
        $time = Carbon::parse($bedtime);

        return (($time->hour * 60 + $time->minute) - 1080 + 1440) % 1440;
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
}
