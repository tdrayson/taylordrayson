<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Date parts pulled out of a timestamp column, in whichever dialect the current
 * connection speaks.
 *
 * SQLite's strftime() has no MySQL equivalent, so these queries ran only on
 * SQLite. Written as expressions rather than casts on the PHP side because the
 * grouping and comparison happen in the database, over every row.
 *
 * Each takes a column name or any SQL expression, e.g. COALESCE(a, b).
 */
final class SqlDate
{
    /**
     * Day of the week as 0-6 with Sunday as 0, matching both SQLite's `%w` and
     * JavaScript's getDay(), which is the order the heatmap's rows are built in.
     *
     * MySQL's DAYOFWEEK() is 1-7 with Sunday as 1, so it is shifted down. Miss
     * that and every reading lands on the wrong day, which no error reports.
     */
    public static function dayOfWeek(string $expression): string
    {
        return self::isMysql()
            ? "(DAYOFWEEK({$expression}) - 1)"
            : "CAST(strftime('%w', {$expression}) AS INTEGER)";
    }

    /**
     * Hour of the day as 0-23.
     */
    public static function hour(string $expression): string
    {
        return self::isMysql()
            ? "HOUR({$expression})"
            : "CAST(strftime('%H', {$expression}) AS INTEGER)";
    }

    /**
     * Month and day as a zero-padded "MM-DD" string, which sorts and compares
     * correctly within a year without carrying the year itself.
     */
    public static function monthDay(string $expression): string
    {
        return self::isMysql()
            ? "DATE_FORMAT({$expression}, '%m-%d')"
            : "strftime('%m-%d', {$expression})";
    }

    private static function isMysql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
}
