<?php

namespace App\Presenters\Exports;

use App\Data\Aspects\MealBreakdown;
use App\Data\Aspects\MealBreakdownItem;
use App\Data\Aspects\MealBreakdownMeal;
use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Enums\TimelineType;
use App\Models\Food;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Presenters\Exports\Sheets\FoodSheet;
use App\Queries\DayFood;
use App\Support\SerialNumber;
use Illuminate\Support\Str;

/**
 * A food day as an export: a whole day's eating aggregated into macro totals
 * plus a per-meal breakdown, not the single row behind it. A MealBreakdown
 * aspect carries that breakdown pre-formatted, which is what lets the sheet
 * print item rows without reading raw item data itself.
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
        $itemCount = $this->itemCount($day['meals']);

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
                ExportField::make('items_logged', 'Items logged', (string) $itemCount, $itemCount),
                ExportField::maybe('owner', 'Name', config('identity.name')),
                ExportField::maybe('receipt_date', 'Date', $this->receiptDate($model)),
                ExportField::make('receipt_number', 'Receipt no.', SerialNumber::for($model->occurred_at), $model->id),
            ])),
            links: CommonLinks::for($model),
            aspects: array_filter([
                MealBreakdown::class => $this->mealBreakdown($day['meals']),
            ]),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $meals
     */
    private function mealBreakdown(array $meals): ?MealBreakdown
    {
        return MealBreakdown::make(array_map(
            fn (array $meal): MealBreakdownMeal => new MealBreakdownMeal(
                Str::headline($meal['meal']),
                array_map(
                    fn (array $item): MealBreakdownItem => new MealBreakdownItem(
                        $this->itemName($item),
                        number_format((int) $item['calories']).' kcal',
                    ),
                    $meal['items'],
                ),
            ),
            $meals,
        ));
    }

    /**
     * An item as a receipt line: its quantity, then its name. The unit is
     * dropped for a bare "serving", which says nothing a receipt needs.
     *
     * @param  array<string, mixed>  $item
     */
    private function itemName(array $item): string
    {
        $quantity = $this->itemQuantity($item);

        return $quantity === '' ? $item['name'] : "{$quantity} {$item['name']}";
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function itemQuantity(array $item): string
    {
        $quantity = (float) $item['quantity'];

        if ($quantity <= 0) {
            return '';
        }

        $number = rtrim(rtrim(number_format($quantity, 1), '0'), '.');
        $units = $item['units'];

        if ($units === null || $units === '' || in_array(mb_strtolower($units), ['serving', 'servings'], true)) {
            return $number;
        }

        return "{$number} {$units}";
    }

    /**
     * @param  array<int, array<string, mixed>>  $meals
     */
    private function itemCount(array $meals): int
    {
        return array_sum(array_map(fn (array $meal): int => count($meal['items']), $meals));
    }

    /**
     * The day alone, no time: a food entry has no real clock time (Rovi
     * gives a date and a meal label, never a time), so the receipt shows the
     * date where a shop receipt would print its address.
     */
    private function receiptDate(Food $model): ?string
    {
        return $model->occurred_at?->format('d M Y');
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
