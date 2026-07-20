<?php

namespace App\Enums;

/**
 * Reference enum for the known `activity.type` values, NOT a model cast.
 *
 * `type` is an open set: Strava mints new sport types via Str::kebab() when
 * no explicit mapping exists (see StravaSync::TYPE_MAP), so the column stays
 * a plain string and unknown values simply won't match a case here.
 */
enum ActivityType: string
{
    case Run = 'run';
    case Walk = 'walk';
    case Ride = 'ride';
    case EbikeRide = 'e-bike-ride';
    case Swim = 'swim';
    case WeightTraining = 'weight-training';
    case Workout = 'workout';
    case Yoga = 'yoga';
    case IceSkate = 'ice-skate';
    case Padel = 'padel';
    case TableTennis = 'table-tennis';

    public function label(): string
    {
        return match ($this) {
            self::Run => 'Run',
            self::Walk => 'Walk',
            self::Ride => 'Ride',
            self::EbikeRide => 'E-bike ride',
            self::Swim => 'Swim',
            self::WeightTraining => 'Weight training',
            self::Workout => 'Workout',
            self::Yoga => 'Yoga',
            self::IceSkate => 'Ice skate',
            self::Padel => 'Padel',
            self::TableTennis => 'Table tennis',
        };
    }
}
