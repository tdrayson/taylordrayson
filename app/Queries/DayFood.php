<?php

namespace App\Queries;

use App\Models\Food;
use Illuminate\Support\Collection;

/**
 * A whole day's food, aggregated once: the day's macro totals plus a
 * per-meal breakdown. A food entry represents the day it fell on rather than
 * a single row, so both the entry page and the export read this.
 */
final class DayFood
{
    /** @var array<string, int> */
    private const MEAL_ORDER = ['breakfast' => 0, 'lunch' => 1, 'dinner' => 2, 'snacks' => 3];

    /**
     * @return array{status: string, inProgress: bool, totals: array<string, float|int>, meals: array<int, array<string, mixed>>}
     */
    public function __invoke(Food $model): array
    {
        $items = Food::query()
            ->whereDate('occurred_at', $model->occurred_at->toDateString())
            ->orderBy('occurred_at')
            ->get();

        return [
            'status' => $model->status->value,
            // True while the day is still today, so a page or export can flag
            // that more food may yet be logged. Computed server-side to avoid
            // client tz math.
            'inProgress' => $model->occurred_at->isToday(),
            'totals' => $this->totals($items),
            'meals' => $this->meals($items),
        ];
    }

    /**
     * @param  Collection<int, Food>  $items
     * @return array<string, float|int>
     */
    private function totals($items): array
    {
        return [
            'calories' => (int) $items->sum('calories'),
            'protein' => round((float) $items->sum('protein'), 1),
            'carbs' => round((float) $items->sum('carbs'), 1),
            'fat' => round((float) $items->sum('fat'), 1),
            'saturated_fat' => round((float) $items->sum('saturated_fat'), 1),
            'sugars' => round((float) $items->sum('sugars'), 1),
            'fibre' => round((float) $items->sum('fibre'), 1),
            'sodium' => (int) round((float) $items->sum('sodium')),
        ];
    }

    /**
     * @param  Collection<int, Food>  $items
     * @return array<int, array<string, mixed>>
     */
    private function meals($items): array
    {
        return $items->groupBy('meal')
            ->map(fn ($group, string $meal): array => [
                'meal' => $meal,
                'calories' => (int) $group->sum('calories'),
                'items' => $group->map(fn (Food $item): array => [
                    'name' => $item->name,
                    'calories' => (int) $item->calories,
                    'quantity' => (float) $item->quantity,
                    'units' => $item->units,
                ])->values()->all(),
            ])
            ->sortBy(fn (array $meal): int => self::MEAL_ORDER[$meal['meal']] ?? 99)
            ->values()
            ->all();
    }
}
