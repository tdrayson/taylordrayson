<?php

namespace App\Queries;

use App\Enums\CoffeeDrink;
use App\Models\Food;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * How many coffees I have logged since 1 January: food rows CoffeeDrink matches, a row
 * logged in countable servings counting as that many.
 */
final class CoffeesThisYear
{
    private const KEY = 'count.coffees-this-year';

    private const COUNTABLE_UNITS = ['serving', 'servings', 'each', 'cup', 'cups', 'drink', 'drinks'];

    /**
     * The count, cached until midnight and dropped whenever food is saved.
     */
    public function __invoke(): int
    {
        return Cache::remember(self::KEY, now()->endOfDay(), fn (): int => $this->count());
    }

    /** Drop the cached count, so the next read recomputes it. */
    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }

    private function count(): int
    {
        return Food::query()
            ->whereBetween('occurred_at', [now()->startOfYear(), now()->endOfYear()])
            ->where(function (Builder $query): void {
                foreach (CoffeeDrink::terms() as $term) {
                    $query->orWhereRaw('LOWER(name) LIKE ?', ["%{$term}%"]);
                }
            })
            ->where(function (Builder $query): void {
                foreach (CoffeeDrink::exclusions() as $term) {
                    $query->whereRaw('LOWER(name) NOT LIKE ?', ["%{$term}%"]);
                }
            })
            ->get(['quantity', 'units'])
            ->sum(fn (Food $food): int => self::servings($food->quantity, $food->units));
    }

    /**
     * How many coffees one row stands for: its quantity in countable units, otherwise 1.
     *
     * @param  float|string|null  $quantity  The logged amount.
     * @param  string|null  $units  The logged unit, e.g. "Servings" or "Milliliters".
     */
    private static function servings(float|string|null $quantity, ?string $units): int
    {
        if (! in_array(mb_strtolower(trim((string) $units)), self::COUNTABLE_UNITS, true)) {
            return 1;
        }

        return max(1, (int) round((float) $quantity));
    }
}
