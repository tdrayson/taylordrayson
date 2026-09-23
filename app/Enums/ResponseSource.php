<?php

namespace App\Enums;

/**
 * Where a response on one of my entries came from, as advanced search offers it.
 * Strava and Swarm share their values with Source, which is what the synced rows store.
 */
enum ResponseSource: string
{
    case Comment = 'comment';
    case Webmention = 'webmention';
    case Strava = 'strava';
    case Swarm = 'swarm';

    public function label(): string
    {
        return match ($this) {
            self::Comment => 'Local comment',
            self::Webmention => 'Webmention',
            self::Strava => 'Strava',
            self::Swarm => 'Swarm',
        };
    }

    /**
     * The values of every source synced from another platform.
     *
     * @return list<string>
     */
    public static function syndicatedValues(): array
    {
        return [self::Strava->value, self::Swarm->value];
    }
}
