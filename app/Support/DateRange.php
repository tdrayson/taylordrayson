<?php

namespace App\Support;

use App\Data\RangeData;
use Carbon\CarbonInterface;

class DateRange
{
    /**
     * Format a span of days the way the site has always shown one: `label` is
     * the compact card form ("2-4 Jun 2022"), `long` the spelled-out detail
     * form ("2nd to 4th June 2022"). Extracted from Event so trips report
     * their window identically rather than inventing a second date format.
     */
    public static function for(CarbonInterface $start, CarbonInterface $end): RangeData
    {
        $start = $start->copy();
        $end = $end->copy();
        $days = $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;

        $sameMonth = $start->format('n') === $end->format('n') && $start->format('Y') === $end->format('Y');
        $sameYear = $start->format('Y') === $end->format('Y');

        $label = $sameMonth
            ? $start->format('j').'-'.$end->format('j M Y')
            : $start->format('j M').' - '.$end->format('j M Y');

        if ($sameMonth) {
            $long = $start->format('jS').' to '.$end->format('jS F Y');
        } elseif ($sameYear) {
            $long = $start->format('jS F').' to '.$end->format('jS F Y');
        } else {
            $long = $start->format('jS F Y').' to '.$end->format('jS F Y');
        }

        return new RangeData(
            start: $start->toDateString(),
            end: $end->toDateString(),
            days: (int) $days,
            label: $label,
            long: $long,
        );
    }
}
