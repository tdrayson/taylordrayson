<?php

namespace App\Support;

/**
 * Reading progress as a percentage, however it was measured.
 */
final class BookProgress
{
    public const FINISHED = 100.0;

    /** Clamp to 0-100 at three decimals, which is all the column holds. */
    public static function round(float $percent): float
    {
        return round(min(max($percent, 0.0), self::FINISHED), 3);
    }

    /**
     * @param  int  $pages  Must be above zero.
     */
    public static function fromPage(int $page, int $pages): float
    {
        return self::round($page / $pages * 100);
    }

    /** Floored, so a book never reads as 100% before it is. */
    public static function display(float $percent): int
    {
        return (int) floor($percent);
    }
}
