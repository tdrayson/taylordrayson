<?php

namespace App\Enums;

/**
 * The reaction set, deliberately positive-only: there is no downvote vector on
 * a personal site.
 *
 * Like is first and separate from Love, because they are separate in the
 * vocabulary this has to interoperate with: a `like-of` is a structured binary
 * property, while the rest are reacji, an emoji somebody chose. An incoming
 * like, a Strava kudo and a Swarm like are all plain approval, so they land on
 * Like rather than being recorded as a heart nobody picked.
 */
enum ReactionType: string
{
    case Like = 'like';
    case Love = 'love';
    case Celebrate = 'celebrate';
    case Wow = 'wow';
    case Haha = 'haha';
    case Sad = 'sad';

    public function label(): string
    {
        return match ($this) {
            self::Like => 'Like',
            self::Love => 'Love',
            self::Celebrate => 'Celebrate',
            self::Wow => 'Wow',
            self::Haha => 'Haha',
            self::Sad => 'Sad',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Like => '👍',
            self::Love => '❤️',
            self::Celebrate => '🎉',
            self::Wow => '😲',
            self::Haha => '😂',
            self::Sad => '😢',
        };
    }

    /** The type an incoming emoji reaction maps to, or null when it matches none. */
    public static function fromEmoji(string $emoji): ?self
    {
        foreach (self::cases() as $case) {
            // Compared without the variation selector, which a sender may or
            // may not include: "❤" and "❤️" are the same reaction.
            if (self::bare($case->emoji()) === self::bare($emoji)) {
                return $case;
            }
        }

        return null;
    }

    private static function bare(string $emoji): string
    {
        return str_replace(["\u{FE0F}", "\u{FE0E}"], '', $emoji);
    }
}
