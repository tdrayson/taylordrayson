<?php

namespace App\Actions;

use App\Models\Calorie;
use App\Models\Sleep;
use App\Models\TimelineEntry;
use App\Presenters\CardPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Per-day data for the month calendar: sleep duration and the day's calorie
 * total (the everyday sub-stats) plus the type of each notable entry for
 * icons — sleep and food are kept out of the icon row since they happen
 * daily and would just clutter every cell.
 */
final class BuildMonthCalendar
{
    /**
     * @param  Collection<int, TimelineEntry>  $entries
     * @return array<int, array{sleep: ?int, calories: ?int, types: array<int, string>}>
     */
    public function __invoke(Collection $entries, Carbon $start, Carbon $end): array
    {
        // Calories store one row per food item but only one spine entry per day,
        // so day totals must come straight from the calories table.
        $calorieTotals = Calorie::query()
            ->toBase()
            ->selectRaw('DATE(occurred_at) as date, SUM(calories) as total')
            ->whereBetween('occurred_at', [$start, $end])
            ->groupBy('date')
            ->pluck('total', 'date');

        return $entries->groupBy(fn (TimelineEntry $entry): int => (int) $entry->occurred_at->format('j'))
            ->map(function (Collection $group) use ($calorieTotals): array {
                $sleep = null;
                $types = [];

                foreach ($group as $entry) {
                    $model = $entry->timelineable;

                    if ($model instanceof Sleep) {
                        $sleep = $model->duration;

                        continue;
                    }

                    if ($model instanceof Calorie) {
                        continue;
                    }

                    $types[] = CardPresenter::for($model)->type->value;
                }

                $calories = (int) ($calorieTotals[$group->first()->occurred_at->toDateString()] ?? 0);

                return ['sleep' => $sleep, 'calories' => $calories ?: null, 'types' => $types];
            })->all();
    }
}
