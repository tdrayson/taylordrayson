<?php

namespace App\Enums;

/** What a Strava webhook event is about. */
enum StravaObjectType: string
{
    case Activity = 'activity';
    case Athlete = 'athlete';

    public function label(): string
    {
        return match ($this) {
            self::Activity => 'Activity',
            self::Athlete => 'Athlete',
        };
    }
}
