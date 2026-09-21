<?php

namespace App\Support;

/**
 * GBP formatting, matching resources/js/lib/format.js so a server-rendered
 * figure and a client-rendered one read identically.
 */
class Money
{
    /** Always two places: 50.6 becomes "£50.60". Null for a blank value. */
    public static function gbp(float|int|string|null $value): ?string
    {
        return $value === null || $value === '' ? null : '£'.number_format((float) $value, 2);
    }

    /**
     * A fuel price the way a forecourt board shows it: 1.619 becomes "161.9p".
     * The one deliberate exception to the two-place rule, because fuel is sold
     * in tenths of a penny.
     */
    public static function pencePerLitre(float|int|string|null $value): ?string
    {
        return $value === null || $value === '' ? null : number_format((float) $value * 100, 1).'p';
    }
}
