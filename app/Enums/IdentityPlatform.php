<?php

namespace App\Enums;

/**
 * Where a subject can be linked from, offered as the identity row's platform
 * list. A reference enum, not a cast: the column stays a plain string so a
 * platform typed in before this list grew still reads back.
 */
enum IdentityPlatform: string
{
    case Website = 'website';
    case Email = 'email';
    case Instagram = 'instagram';
    case Threads = 'threads';
    case Bluesky = 'bluesky';
    case Mastodon = 'mastodon';
    case X = 'x';
    case Facebook = 'facebook';
    case LinkedIn = 'linkedin';
    case GitHub = 'github';
    case YouTube = 'youtube';
    case Strava = 'strava';
    case Untappd = 'untappd';
    case Letterboxd = 'letterboxd';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Email => 'Email',
            self::Instagram => 'Instagram',
            self::Threads => 'Threads',
            self::Bluesky => 'Bluesky',
            self::Mastodon => 'Mastodon',
            self::X => 'X',
            self::Facebook => 'Facebook',
            self::LinkedIn => 'LinkedIn',
            self::GitHub => 'GitHub',
            self::YouTube => 'YouTube',
            self::Strava => 'Strava',
            self::Untappd => 'Untappd',
            self::Letterboxd => 'Letterboxd',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $platform): array => ['value' => $platform->value, 'label' => $platform->label()],
            self::cases(),
        );
    }
}
