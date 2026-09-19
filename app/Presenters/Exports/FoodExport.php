<?php

namespace App\Presenters\Exports;

use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Enums\TimelineType;
use App\Models\Food;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Queries\DayFood;
use Illuminate\Support\Str;

/**
 * A food day as an export: a whole day's eating aggregated into macro totals
 * plus a per-meal breakdown, not the single row behind it. No aspects: a food
 * day has neither a place nor a span.
 */
final class FoodExport
{
    public function present(Food $model): ExportData
    {
        $day = app(DayFood::class)($model);
        $card = CardPresenter::for($model);
        $totals = $day['totals'];

        return new ExportData(
            type: TimelineType::Food,
            url: url($model->url()),
            title: $card->title,
            summary: EntryDescription::for($model, $card),
            occurred: $model->occurred_at === null ? null : ExportInstant::for($model->occurred_at, $model->timezone()),
            fields: array_values(array_filter([
                ExportField::make('calories', 'Calories', number_format($totals['calories']).' kcal', $totals['calories']),
                ExportField::make('protein', 'Protein', round($totals['protein'], 1).'g', $totals['protein']),
                ExportField::make('carbs', 'Carbohydrate', round($totals['carbs'], 1).'g', $totals['carbs']),
                ExportField::make('fat', 'Fat', round($totals['fat'], 1).'g', $totals['fat']),
                ExportField::make('saturated_fat', 'Saturates', round($totals['saturated_fat'], 1).'g', $totals['saturated_fat']),
                ExportField::make('sugars', 'Sugars', round($totals['sugars'], 1).'g', $totals['sugars']),
                ExportField::make('fibre', 'Fibre', round($totals['fibre'], 1).'g', $totals['fibre']),
                ExportField::make('sodium', 'Sodium', round($totals['sodium']).'mg', $totals['sodium']),
                ExportField::maybe('meals', 'Meals', $this->mealsDisplay($day['meals']), $day['meals']),
            ])),
            links: CommonLinks::for($model),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $meals
     */
    private function mealsDisplay(array $meals): ?string
    {
        if ($meals === []) {
            return null;
        }

        return collect($meals)
            ->map(fn (array $meal): string => Str::headline($meal['meal'])." ({$meal['calories']} kcal)")
            ->implode(', ');
    }
}
