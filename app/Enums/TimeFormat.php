<?php

namespace App\Enums;

enum TimeFormat: string
{
    case TwelveHour = '12h';
    case TwentyFourHour = '24h';

    public function label(): string
    {
        return match ($this) {
            self::TwelveHour => '12-hour',
            self::TwentyFourHour => '24-hour',
        };
    }
}
