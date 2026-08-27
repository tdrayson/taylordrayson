<?php

namespace App\Queries;

use App\Models\Calorie;
use App\Support\SqlDate;
use Illuminate\Support\Collection;

/**
 * A day's food totals, read once per day per request.
 *
 * Every food card shows the whole day rather than the single item behind it, so
 * a feed of thirty cards asked for the same totals over and over. Bounded by a
 * range on `occurred_at` rather than a function over it, which is what lets the
 * index apply.
 */
final class DayFoodTotals
{
    private const EMPTY = ['calories' => 0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0, 'meals' => 0];

    /** @var array<string, array{calories: int, protein: float, carbs: float, fat: float, meals: int}> */
    private array $totals = [];

    /**
     * Read every day in one query, so a feed costs one rather than one per card.
     *
     * @param  list<string>  $dates  Calendar dates as Y-m-d.
     */
    public function warm(array $dates): void
    {
        $wanted = array_values(array_diff(array_unique($dates), array_keys($this->totals)));

        if ($wanted === []) {
            return;
        }

        $this->rowsBetween(min($wanted), max($wanted))
            ->each(function (object $row): void {
                $this->totals[(string) $row->day] = [
                    'calories' => (int) $row->calories,
                    'protein' => (float) $row->protein,
                    'carbs' => (float) $row->carbs,
                    'fat' => (float) $row->fat,
                    'meals' => (int) $row->meals,
                ];
            });

        // Days with nothing logged are recorded as zero, or every card for one
        // would ask again.
        foreach ($wanted as $date) {
            $this->totals[$date] ??= self::EMPTY;
        }
    }

    /**
     * @return array{calories: int, protein: float, carbs: float, fat: float, meals: int}
     */
    public function for(string $date): array
    {
        if (! array_key_exists($date, $this->totals)) {
            $this->warm([$date]);
        }

        return $this->totals[$date];
    }

    /**
     * A half-open range, so `occurred_at` is compared as itself. Wrapping it in
     * a date function instead makes the index unusable and scans the table.
     *
     * @return Collection<int, object>
     */
    private function rowsBetween(string $from, string $to): Collection
    {
        $day = SqlDate::date('occurred_at');

        return Calorie::query()
            ->selectRaw("{$day} as day, SUM(calories) as calories, SUM(protein) as protein, SUM(carbs) as carbs, SUM(fat) as fat, COUNT(DISTINCT meal) as meals")
            ->where('occurred_at', '>=', $from.' 00:00:00')
            ->where('occurred_at', '<', date('Y-m-d', strtotime($to.' +1 day')).' 00:00:00')
            ->groupByRaw($day)
            ->get();
    }
}
