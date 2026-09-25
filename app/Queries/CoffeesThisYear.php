<?php

namespace App\Queries;

use App\Enums\CoffeeDrink;
use App\Models\Food;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * How many coffees I have logged since 1 January, counting every food item named like one.
 */
final class CoffeesThisYear
{
    private const KEY = 'count.coffees-this-year';

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
            ->count();
    }
}
