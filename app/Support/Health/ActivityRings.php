<?php

namespace App\Support\Health;

use Illuminate\Support\Carbon;

/**
 * Reduce a Health Auto Export activity send to today's ring values.
 *
 * HAE reports quantities as samples rather than as a day's total, so every
 * metric is summed. With the automation's "summarise data" on that is one
 * sample already deduplicated across the Watch and the phone; with it off it is
 * thousands of fragments which, for steps, double-count where the two devices
 * overlap. Summing handles both, and the summarised send is the accurate one.
 */
class ActivityRings
{
    /**
     * HAE metric name to the ring field it feeds. `apple_stand_hour` rather than
     * `apple_stand_time` because the stand ring counts hours, not minutes.
     *
     * @var array<string, string>
     */
    public const METRICS = [
        'active_energy' => 'move',
        'apple_exercise_time' => 'exercise',
        'apple_stand_hour' => 'stand',
        'step_count' => 'steps',
    ];

    /**
     * Today's ring values, keyed as the `rings` state group expects. Only the
     * metrics actually sent appear, so a send omitting one leaves the stored
     * value alone; a metric sent with no samples is a real zero and is included.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, int>
     */
    public static function from(array $payload): array
    {
        $today = Carbon::now(config('app.home_timezone'))->toDateString();
        $rings = [];

        foreach ($payload['data']['metrics'] ?? [] as $metric) {
            $field = self::METRICS[$metric['name'] ?? ''] ?? null;

            if ($field === null || ! is_array($metric['data'] ?? null)) {
                continue;
            }

            $rings[$field] = self::total($metric['data'], $today);
        }

        return $rings;
    }

    /**
     * Sum the samples falling on $today, dropping the rest. A send that lands
     * just after midnight carries the day that has just ended, and writing that
     * into the rings would show yesterday's totals as though they were now.
     *
     * @param  array<int, mixed>  $samples
     */
    private static function total(array $samples, string $today): int
    {
        $total = 0.0;

        foreach ($samples as $sample) {
            if (! is_array($sample) || ! is_string($sample['date'] ?? null)) {
                continue;
            }

            if (Carbon::parse($sample['date'])->toDateString() === $today) {
                $total += (float) ($sample['qty'] ?? 0);
            }
        }

        return (int) round($total);
    }
}
