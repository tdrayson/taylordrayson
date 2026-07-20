<?php

namespace App\Queries;

use App\Models\Activity;
use App\Models\Calorie;
use App\Models\Sleep;
use App\Models\TimelineEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Summary stats for a day, derived from the entries we actually store.
 */
final class DayStats
{
    /**
     * @param  Collection<int, TimelineEntry>  $entries
     * @return array<int, array{label: string, value?: string, unit?: string, seconds?: int, distanceM?: int, precision?: int}>
     */
    public function __invoke(Collection $entries, Carbon $date): array
    {
        $models = $entries->map->timelineable;
        $sleep = $models->first(fn ($model): bool => $model instanceof Sleep);
        $activities = $models->filter(fn ($model): bool => $model instanceof Activity);

        $stats = [];

        if ($sleep instanceof Sleep) {
            $stats[] = ['label' => 'Slept', 'seconds' => $sleep->duration];
        }

        if ($activities->isNotEmpty()) {
            $stats[] = ['label' => 'Activities', 'value' => (string) $activities->count()];

            $distanceM = (int) round($activities->sum('distance'));

            if ($distanceM > 0) {
                $stats[] = ['label' => 'Distance', 'distanceM' => $distanceM, 'precision' => 1];
            }
        }

        // Calories are stored one row per food item, so total the whole day directly
        // rather than the single representative row carried on the timeline entry.
        $calories = (int) Calorie::query()->whereDate('occurred_at', $date->toDateString())->sum('calories');

        if ($calories > 0) {
            $stats[] = ['label' => 'Food', 'value' => number_format($calories), 'unit' => 'kcal'];
        }

        return $stats;
    }
}
