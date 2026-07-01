<?php

namespace App\Support\Health;

use App\Models\Activity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HeartRateMatcher
{
    /**
     * Reduce raw heart_rate samples onto the activities whose time window they
     * fall within. Each activity's window is its start instant through its
     * elapsed (wall-clock) duration, so paused workouts still capture their
     * full heart-rate trace. Where more than one device reports inside the
     * window the source with the most samples wins, avoiding double counting
     * when an Apple Watch and an Oura ring overlap.
     *
     * @param  array<int, array{time: int, avg: int, max: int, source: string}>  $samples
     * @param  Collection<int, Activity>  $activities
     * @return array<int, array{avg: int, max: int, series: list<array{time: string, bpm: int}>}>
     *                                                                                            Keyed by activity id, derived from these samples alone.
     */
    public function match(array $samples, Collection $activities): array
    {
        if ($samples === []) {
            return [];
        }

        usort($samples, fn (array $first, array $second): int => $first['time'] <=> $second['time']);
        $times = array_column($samples, 'time');
        $count = count($times);
        $matched = [];

        foreach ($activities as $activity) {
            $start = $this->windowStart($activity);
            $seconds = (int) (data_get($activity->meta, 'elapsed_time') ?? $activity->duration);

            if ($start === null || $seconds <= 0) {
                continue;
            }

            $end = $start + $seconds;
            $window = [];

            for ($index = $this->lowerBound($times, $start); $index < $count && $times[$index] <= $end; $index++) {
                $window[] = $samples[$index];
            }

            if ($window === []) {
                continue;
            }

            $matched[$activity->id] = $this->aggregate($this->dominantSource($window));
        }

        return $matched;
    }

    /**
     * The activity's real UTC start instant. occurred_at is stored as local
     * wall-clock, so it must be read through the activity's timezone before
     * comparing against the real-UTC sample stamps; otherwise summer (DST)
     * activities are windowed an hour adrift and miss their samples.
     */
    private function windowStart(Activity $activity): ?int
    {
        if ($activity->occurred_at === null) {
            return null;
        }

        return Carbon::parse(
            $activity->occurred_at->format('Y-m-d H:i:s'),
            $activity->timezone ?? 'UTC'
        )->getTimestamp();
    }

    /**
     * Keep only the samples from the source that reported most often inside the
     * window, so a single device's trace is used rather than a blend of two.
     *
     * @param  list<array{time: int, avg: int, max: int, source: string}>  $window
     * @return list<array{time: int, avg: int, max: int, source: string}>
     */
    private function dominantSource(array $window): array
    {
        $bySource = [];

        foreach ($window as $sample) {
            $bySource[$sample['source']][] = $sample;
        }

        uasort($bySource, fn (array $first, array $second): int => count($second) <=> count($first));

        return reset($bySource);
    }

    /**
     * @param  list<array{time: int, avg: int, max: int, source: string}>  $samples
     * @return array{avg: int, max: int, series: list<array{time: string, bpm: int}>}
     */
    private function aggregate(array $samples): array
    {
        $averages = array_column($samples, 'avg');

        return [
            'avg' => (int) round(array_sum($averages) / count($averages)),
            'max' => max(array_column($samples, 'max')),
            'series' => array_map(fn (array $sample): array => [
                'time' => Carbon::createFromTimestampUTC($sample['time'])->format('Y-m-d H:i:s'),
                'bpm' => $sample['avg'],
            ], $samples),
        ];
    }

    /**
     * Index of the first timestamp at or after the target.
     *
     * @param  list<int>  $times
     */
    private function lowerBound(array $times, int $target): int
    {
        $low = 0;
        $high = count($times);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);

            if ($times[$middle] < $target) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }
}
