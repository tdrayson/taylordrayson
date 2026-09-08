<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/** The named date renderings a tag may ask for. */
enum DateFormat: string
{
    case Date = 'date';
    case Long = 'long';
    case Month = 'month';
    case DayMonth = 'day-month';
    case Year = 'year';
    case Time = 'time';
    case DateTime = 'datetime';
    case Relative = 'relative';

    public function label(): string
    {
        return match ($this) {
            self::Date => '19 Feb 2003',
            self::Long => '19th February 2003',
            self::Month => 'February 2003',
            self::DayMonth => '19 February',
            self::Year => '2003',
            self::Time => '11:20am',
            self::DateTime => 'Wed 19 Feb 2003, 11:20am',
            self::Relative => '23 years ago',
        };
    }

    public function apply(CarbonInterface $at): string
    {
        return match ($this) {
            self::Date => $at->format('j M Y'),
            self::Long => $at->format('jS F Y'),
            self::Month => $at->format('F Y'),
            self::DayMonth => $at->format('j F'),
            self::Year => $at->format('Y'),
            self::Time => $at->format('g:ia'),
            self::DateTime => $at->format('D j M Y, g:ia'),
            self::Relative => $at->diffForHumans(),
        };
    }
}
