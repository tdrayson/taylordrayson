<?php

namespace App\Enums;

/**
 * Where a response on one of my entries came from, one case per table that
 * holds them. Which platform a synced response came from is Source's to say.
 */
enum ResponseSource: string
{
    case Comment = 'comment';
    case Webmention = 'webmention';
    case Syndicated = 'syndicated';

    public function label(): string
    {
        return match ($this) {
            self::Comment => 'Local comment',
            self::Webmention => 'Webmention',
            self::Syndicated => 'Platform',
        };
    }
}
