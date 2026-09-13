<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How many days belong on one page of a feed.
 *
 * A page is a run of whole days, never part of one, so the size flexes with how
 * much each day holds: a dense week fills a page on its own, a sparse month
 * fits in one. Three limits, whichever binds first.
 */
final class DayBudget
{
    /**
     * Entries a page aims for. Soft: a single day carrying more than this still
     * renders whole, since the alternative is splitting it.
     */
    public const ENTRY_BUDGET = 50;

    /**
     * Calendar days a page may span. Binds in the sparse early years, where the
     * budget alone would sweep 2003 to 2011 into one page and label it as such.
     */
    public const MAX_SPAN = 92;

    /** Logged days a page may hold, so a long quiet stretch still terminates. */
    public const MAX_DAYS = 31;

    /**
     * The days that fit on one page, starting from the first candidate.
     *
     * @param  Collection<int, array{day: string, total: int}>  $candidates
     * @return Collection<int, array{day: string, total: int}>
     */
    public static function fill(Collection $candidates): Collection
    {
        $taken = collect();
        $entries = 0;

        foreach ($candidates as $day) {
            if ($taken->isNotEmpty()) {
                $overBudget = $entries + $day['total'] > self::ENTRY_BUDGET;
                $overSpan = self::span($taken->first()['day'], $day['day']) > self::MAX_SPAN;

                if ($overBudget || $overSpan || $taken->count() >= self::MAX_DAYS) {
                    break;
                }
            }

            $taken->push($day);
            $entries += $day['total'];
        }

        return $taken;
    }

    /**
     * Every page of a period, in order.
     *
     * Page numbers need boundaries computed up front, because with a flexing
     * page size where page 7 starts depends on the six before it. The day
     * counts for a whole year are at most 366 rows, so this is cheap.
     *
     * @param  Collection<int, array{day: string, total: int}>  $days
     * @return Collection<int, Collection<int, array{day: string, total: int}>>
     */
    public static function pages(Collection $days): Collection
    {
        $pages = collect();
        $remaining = $days->values();

        while ($remaining->isNotEmpty()) {
            $page = self::fill($remaining);
            $pages->push($page);
            $remaining = $remaining->slice($page->count())->values();
        }

        return $pages;
    }

    /** Whole calendar days between two Y-m-d strings, in either order. */
    private static function span(string $a, string $b): int
    {
        return (int) Carbon::parse($a)->diffInDays(Carbon::parse($b), absolute: true);
    }
}
