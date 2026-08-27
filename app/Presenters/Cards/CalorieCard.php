<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Calorie;
use App\Queries\DayFoodTotals;
use App\Support\Text;
use Illuminate\Support\Str;

/**
 * Builds the timeline card for a Calorie entry: the day's total kcal as the
 * title, with the macro breakdown written as a sentence beneath it.
 */
final class CalorieCard
{
    public function present(Calorie $model): CardData
    {
        $totals = app(DayFoodTotals::class)->for($model->occurred_at->toDateString());
        $kcal = number_format($totals['calories']);

        return new CardData(
            type: TimelineType::Calorie,
            icon: 'utensils',
            title: "{$kcal} kcal for the day",
            titleLabel: "Food log, {$kcal} kcal for the day",
            subtitle: $this->sentence($totals, $model),
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'food',
            range: null,
            meta: CardMeta::empty(),
        );
    }

    /**
     * The day's macros as a sentence. Food is the only all-day type, so the
     * date is named here: its card shows "All day" where the others show a
     * clock, and the sentence is what has to stand alone in a feed reader.
     *
     * @param  array{calories: int, protein: float, carbs: float, fat: float, meals: int}  $totals
     */
    private function sentence(array $totals, Calorie $model): ?string
    {
        $macros = array_values(array_filter([
            $totals['protein'] ? round($totals['protein']).'g protein' : null,
            $totals['carbs'] ? round($totals['carbs']).'g carbs' : null,
            $totals['fat'] ? round($totals['fat']).'g fat' : null,
        ]));

        $date = $model->occurred_at->format('D j M');
        $where = $totals['meals']
            ? sprintf('across %d %s on %s', $totals['meals'], Str::plural('meal', $totals['meals']), $date)
            : "on {$date}";

        if ($macros === []) {
            return "I ate this {$where}.";
        }

        return sprintf('I ate this %s, made up of %s.', $where, Text::sentenceList($macros));
    }
}
