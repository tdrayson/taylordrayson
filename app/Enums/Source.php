<?php

namespace App\Enums;

/**
 * Reference enum for the known `source` values, NOT a model cast.
 *
 * `source` spans five models (Activity, Checkin, Media, Sleep, Calorie) with
 * legacy/aliased values, so the column stays a plain string and unknown
 * values simply won't match a case here.
 */
enum Source: string
{
    case Strava = 'strava';
    case Setgraph = 'setgraph';
    case AppleWatch = 'apple_watch';
    case Clock = 'clock';
    case Health = 'health';
    case Iphone = 'iphone';
    case Oura = 'oura';
    case LoseIt = 'loseit';
    case Rovi = 'rovi';
    case Swarm = 'swarm';
    case Trakt = 'trakt';

    public function label(): string
    {
        return match ($this) {
            self::Strava => 'Strava',
            self::Setgraph => 'Setgraph',
            self::AppleWatch => 'Apple Watch',
            self::Clock => 'Clock',
            self::Health => 'Health',
            self::Iphone => 'iPhone',
            self::Oura => 'Oura',
            self::LoseIt => 'Lose It',
            self::Rovi => 'Rovi',
            self::Swarm => 'Swarm',
            self::Trakt => 'Trakt',
        };
    }
}
