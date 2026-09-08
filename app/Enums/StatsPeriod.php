<?php

namespace App\Enums;

/** The named windows a period option accepts, alongside a bare year. */
enum StatsPeriod: string
{
    case AllTime = 'all-time';
    case ThisYear = 'this-year';
    case LastYear = 'last-year';
    case ThisMonth = 'this-month';
    case LastMonth = 'last-month';
    case Last7Days = 'last-7-days';
    case Last30Days = 'last-30-days';
    case Last90Days = 'last-90-days';
    case Last12Months = 'last-12-months';

    public function label(): string
    {
        return match ($this) {
            self::AllTime => 'All time',
            self::ThisYear => 'This year',
            self::LastYear => 'Last year',
            self::ThisMonth => 'This month',
            self::LastMonth => 'Last month',
            self::Last7Days => 'Last 7 days',
            self::Last30Days => 'Last 30 days',
            self::Last90Days => 'Last 90 days',
            self::Last12Months => 'Last 12 months',
        };
    }
}
