<?php

namespace App\Enums;

/**
 * Reference enum for the `source` values the app gives behaviour to, NOT a model
 * cast. `source` spans five models with legacy and aliased values, so the column
 * stays a string and unknown values match no case here.
 */
enum Source: string
{
    case Strava = 'strava';
    case Swarm = 'swarm';
    case Trakt = 'trakt';
    case Oura = 'oura';
    case AppleWatch = 'apple_watch';
    case Iphone = 'iphone';
    case Rovi = 'rovi';
    case Setgraph = 'setgraph';

    public function label(): string
    {
        return match ($this) {
            self::Strava => 'Strava',
            self::Swarm => 'Swarm',
            self::Trakt => 'Trakt',
            self::Oura => 'Oura',
            self::AppleWatch => 'Apple Watch',
            self::Iphone => 'iPhone',
            self::Rovi => 'Rovi',
            self::Setgraph => 'Setgraph',
        };
    }
}
