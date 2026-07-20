<?php

namespace App\Enums;

/**
 * Reference enum for the `activity.type` values the app gives behaviour to,
 * NOT a model cast and NOT an enumeration of Strava's sport types.
 *
 * `type` is an open set: Strava mints new sport types via Str::kebab() when
 * no explicit mapping exists (see StravaSync::TYPE_MAP), so the column stays
 * a plain string and unknown values simply won't match a case here.
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
