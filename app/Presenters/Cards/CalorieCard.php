<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Calorie;
use App\Queries\DayFoodTotals;
use App\Support\Text;

/**
 * Builds the timeline card for a Calorie entry: the day's total calories as the
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
            title: "I ate {$kcal} calories",
            titleLabel: "Food log, I ate {$kcal} calories",
            subtitle: $this->sentence($totals),
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'food',
            range: null,
            meta: CardMeta::empty(),
        );
    }

    /**
     * The day's macros as a sentence. The title says what was eaten, so this
     * breaks it down rather than repeating the verb, and names no date: the
     * date-group heading carries one, and EntryDescription writes the standalone
     * sentence for the surfaces that have no heading above them.
     *
     * No meal count: the rows carry a meal slot (breakfast/lunch/dinner/snacks),
     * so counting them counts groupings rather than meals eaten.
     *
     * @param  array{calories: int, protein: float, carbs: float, fat: float}  $totals
     */
    private function sentence(array $totals): ?string
    {
        $macros = array_values(array_filter([
            $totals['protein'] ? round($totals['protein']).'g of protein' : null,
            $totals['carbs'] ? round($totals['carbs']).'g of carbs' : null,
            $totals['fat'] ? round($totals['fat']).'g of fat' : null,
        ]));

        return $macros === [] ? null : sprintf('That was %s.', Text::sentenceList($macros));
    }
}
