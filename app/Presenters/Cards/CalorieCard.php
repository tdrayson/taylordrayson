<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Calorie;
use App\Queries\DayFoodTotals;

/**
 * Builds the timeline card for a Calorie entry: the day's total kcal as the
 * title, plus a protein/carbs/fat macro breakdown for the day as the subtitle.
 */
final class CalorieCard
{
    public function present(Calorie $model): CardData
    {
        $totals = app(DayFoodTotals::class)->for($model->occurred_at->toDateString());
        $dailyTotal = $totals['calories'];

        return new CardData(
            type: TimelineType::Calorie,
            icon: 'utensils',
            title: number_format($dailyTotal).' kcal',
            titleLabel: 'Food log, '.number_format($dailyTotal).' kcal for the day',
            subtitle: $this->cardSubtitle($totals),
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'food',
            range: null,
            meta: CardMeta::empty(),
        );
    }

    /**
     * @param  array{calories: int, protein: float, carbs: float, fat: float}  $totals
     */
    private function cardSubtitle(array $totals): ?string
    {
        $parts = [];

        if ($totals['protein']) {
            $parts[] = round($totals['protein']).'g protein';
        }

        if ($totals['carbs']) {
            $parts[] = round($totals['carbs']).'g carbs';
        }

        if ($totals['fat']) {
            $parts[] = round($totals['fat']).'g fat';
        }

        return $parts ? implode(', ', $parts) : null;
    }
}
