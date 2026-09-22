<?php

namespace App\Enums;

enum DateFormat: string
{
    case Short = 'short';
    case Long = 'long';
    case DayMonthYear = 'dmy';
    case MonthDayYear = 'mdy';
    case Iso = 'iso';

    public function label(): string
    {
        return match ($this) {
            self::Short => 'Short',
            self::Long => 'Long',
            self::DayMonthYear => 'Day/month/year',
            self::MonthDayYear => 'Month/day/year',
            self::Iso => 'ISO 8601',
        };
    }

    /** Whether the format is all digits, which never collapses a shared month or year in a range. */
    public function isNumeric(): bool
    {
        return in_array($this, [self::DayMonthYear, self::MonthDayYear, self::Iso], true);
    }
}
