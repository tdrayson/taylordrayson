<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Food;
use App\Queries\DayFoodTotals;
use App\Support\Text;

/**
 * Builds the timeline card for a Food entry: the day's total calories as the
 * title, with the macro breakdown written as a sentence beneath it.
 */
final class FoodCard
{
    public function present(Food $model): CardData
    {
        $totals = app(DayFoodTotals::class)->for($model->occurred_at->toDateString());
        $kcal = number_format($totals['calories']);

        return new CardData(
            type: $this->type(),
            title: $this->title($model),
            titleLabel: "Food log, I ate {$kcal} calories",
            subtitle: $this->sentence($totals),
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            range: null,
            meta: CardMeta::empty(),
        );
    }

    /** The day's total and macros for a reader with no title above it; empty when no macros were logged. */
    public function description(Food $model): string
    {
        $totals = app(DayFoodTotals::class)->for($model->occurred_at->toDateString());
        $macros = $this->macros($totals);

        return $macros === []
            ? ''
            : sprintf("That day's food came to %s calories, with %s.", number_format($totals['calories']), Text::sentenceList($macros));
    }

    /**
     * The day's macros as a sentence under a title that already says what was eaten.
     * No meal count: rows carry a meal slot, so counting them counts groupings.
     *
     * @param  array{calories: int, protein: float, carbs: float, fat: float}  $totals
     */
    private function sentence(array $totals): ?string
    {
        $macros = $this->macros($totals);

        return $macros === [] ? null : sprintf('That was %s.', Text::sentenceList($macros));
    }

    /**
     * @param  array{calories: int, protein: float, carbs: float, fat: float}  $totals
     * @return list<string>
     */
    private function macros(array $totals): array
    {
        return array_values(array_filter([
            $totals['protein'] ? round($totals['protein']).'g of protein' : null,
            $totals['carbs'] ? round($totals['carbs']).'g of carbs' : null,
            $totals['fat'] ? round($totals['fat']).'g of fat' : null,
        ]));
    }

    /**
     * Reads the day's totals, so this is the one card title that costs a query.
     */
    public function title(Food $model): string
    {
        $totals = app(DayFoodTotals::class)->for($model->occurred_at->toDateString());

        return 'I ate '.number_format($totals['calories']).' calories';
    }

    public function type(): TimelineType
    {
        return TimelineType::Food;
    }
}
