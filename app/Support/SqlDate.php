<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Date parts pulled out of a timestamp column, in whichever dialect the current
 * connection speaks. Each takes a column name or any SQL expression, e.g.
 * COALESCE(a, b), since the grouping happens in the database over every row.
 */
final class SqlDate
{
    /**
     * Day of the week as 0-6, Sunday first, matching SQLite's `%w` and JS
     * getDay(). MySQL's DAYOFWEEK() is 1-7, so it is shifted down.
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

    /**
     * The month as an integer, 1-12.
     */
    public static function month(string $expression): string
    {
        return self::isMysql()
            ? "MONTH({$expression})"
            : "CAST(strftime('%m', {$expression}) AS INTEGER)";
    }

    /**
     * The four-digit year as an integer.
     */
    public static function year(string $expression): string
    {
        return self::isMysql()
            ? "YEAR({$expression})"
            : "CAST(strftime('%Y', {$expression}) AS INTEGER)";
    }

    /**
     * The calendar date as a "YYYY-MM-DD" string, dropping the time.
     */
    public static function date(string $expression): string
    {
        return self::isMysql()
            ? "DATE({$expression})"
            : "strftime('%Y-%m-%d', {$expression})";
    }

    private static function isMysql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
}
