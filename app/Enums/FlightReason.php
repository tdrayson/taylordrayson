<?php

namespace App\Enums;

/**
 * Why a flight was taken.
 *
 * A closed set the app defines rather than anything a third party supplies, so
 * it is cast on the model. Adding a reason is a case here and a deploy, which
 * is the point: two spellings of "business" would split the flight story's
 * counts in half without anyone noticing.
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
