<?php

namespace App\Enums;

/**
 * The reaction set, deliberately positive-only: there is no downvote vector on
 * a personal site. Love is also the bucket an incoming webmention `like-of`
 * folds into, so on-site hearts and off-site likes count as one thing.
 */
enum ReactionType: string
{
    case Love = 'love';
    case Celebrate = 'celebrate';
    case Wow = 'wow';
    case Haha = 'haha';
    case Sad = 'sad';

    public function label(): string
    {
        return match ($this) {
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
