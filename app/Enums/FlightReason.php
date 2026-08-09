<?php

namespace App\Enums;

/**
 * Why a flight was taken. A closed set the app defines, so it is cast on the
 * model: two spellings of "business" would silently halve the flight story's
 * counts.
 */
enum FlightReason: string
{
    case Business = 'business';
    case Personal = 'personal';
    case Holiday = 'holiday';

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Business',
            self::Personal => 'Personal',
            self::Holiday => 'Holiday',
        };
    }
}
