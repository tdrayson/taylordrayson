<?php

namespace App\Enums;

/**
 * Reference enum for the `activity.type` values the app gives behaviour to, NOT a
 * model cast. `type` is an open set: Strava mints new sport types via Str::kebab(),
 * so the column stays a string and unknown values match no case here.
 */
enum ActivityDiscipline: string
{
    case Run = 'run';
    case Walk = 'walk';
    case Ride = 'ride';
    case EbikeRide = 'e-bike-ride';
    case Swim = 'swim';

    public function label(): string
    {
        return match ($this) {
            self::Run => 'Run',
            self::Walk => 'Walk',
            self::Ride => 'Ride',
            self::EbikeRide => 'E-bike ride',
            self::Swim => 'Swim',
        };
    }
}
