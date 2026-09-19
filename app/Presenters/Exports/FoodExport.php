<?php

namespace App\Presenters\Exports;

use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Enums\TimelineType;
use App\Models\Food;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Presenters\Exports\Sheets\FoodSheet;
use App\Queries\DayFood;
use Illuminate\Support\Str;

/**
 * A food day as an export: a whole day's eating aggregated into macro totals
 * plus a per-meal breakdown, not the single row behind it. No aspects: a food
 * day has neither a place nor a span.
 */
final class FoodExport
{
    public function sheet(): FoodSheet
    {
        return new FoodSheet;
    }

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
                ExportField::make('protein', 'Protein', $this->grams($totals['protein']), $totals['protein']),
                ExportField::make('carbs', 'Carbohydrate', $this->grams($totals['carbs']), $totals['carbs']),
                ExportField::make('fat', 'Fat', $this->grams($totals['fat']), $totals['fat']),
                ExportField::make('saturated_fat', 'Saturates', $this->grams($totals['saturated_fat']), $totals['saturated_fat']),
                ExportField::make('sugars', 'Sugars', $this->grams($totals['sugars']), $totals['sugars']),
                ExportField::make('fibre', 'Fibre', $this->grams($totals['fibre']), $totals['fibre']),
                ExportField::make('sodium', 'Sodium', number_format($totals['sodium']).'mg', $totals['sodium']),
                ExportField::maybe('meals', 'Meals', $this->mealsDisplay($day['meals']), $day['meals']),
            ])),
            links: CommonLinks::for($model),
        );
    }

    /**
     * A macro in grams to one decimal place, trimmed of a redundant ".0".
     * `number_format` rather than `round()`, so an implausible value (junk
     * data) renders as a plain number rather than scientific notation.
     */
    private function grams(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.').'g';
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
