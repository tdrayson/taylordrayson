<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Strava's sport type mapped to ours.
 *
 * Strava mints new sport types without notice, so anything unmapped falls
 * through to its kebab-case form rather than a default: an unknown type is
 * still a truthful one.
 */
final class StravaActivityType
{
    /** @var array<string, string> */
    private const MAP = [
        'Run' => 'run',
        'TrailRun' => 'run',
        'VirtualRun' => 'run',
        'Walk' => 'walk',
        'Hike' => 'walk',
        'Ride' => 'ride',
        'VirtualRide' => 'ride',
        'GravelRide' => 'ride',
        'MountainBikeRide' => 'ride',
        'EBikeRide' => 'e-bike-ride',
        'EMountainBikeRide' => 'e-bike-ride',
        'Swim' => 'swim',
        'Workout' => 'workout',
        'WeightTraining' => 'weight-training',
        'Yoga' => 'yoga',
        'IceSkate' => 'ice-skate',
        'Squash' => 'workout',
        'Tennis' => 'workout',
        'Badminton' => 'workout',
        'Racquetball' => 'workout',
        'Pickleball' => 'workout',
        'Soccer' => 'workout',
        'Crossfit' => 'workout',
        'HighIntensityIntervalTraining' => 'workout',
        'Elliptical' => 'workout',
        'StairStepper' => 'workout',
        'Rowing' => 'workout',
        'Pilates' => 'workout',
    ];

    /** @param  array<string, mixed>  $data  A Strava summary or detail payload. */
    public static function for(array $data): string
    {
        $sportType = $data['sport_type'] ?? $data['type'] ?? 'Workout';

        return self::MAP[$sportType] ?? Str::kebab($sportType);
    }
}
