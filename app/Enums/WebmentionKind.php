<?php

namespace App\Enums;

/**
 * What an incoming mention claims to be, from the microformats2 property the
 * source used.
 *
 * A reference enum, NOT cast on the model: the vocabulary belongs to other
 * people's sites, so an unfamiliar value must read back as a plain string
 * rather than throwing. Match with `tryFrom()` and treat null as a bare mention.
 */
enum WebmentionKind: string
{
    case Reply = 'reply';
    case Like = 'like';
    case Repost = 'repost';
    case Bookmark = 'bookmark';
    case Rsvp = 'rsvp';
    case Mention = 'mention';

    public function label(): string
    {
        return match ($this) {
            self::Reply => 'Reply',
            self::Like => 'Like',
            self::Repost => 'Repost',
            self::Bookmark => 'Bookmark',
            self::Rsvp => 'RSVP',
            self::Mention => 'Mention',
        };
    }

    /** The mf2 property that signals each kind, in the order they take precedence. */
    public static function fromProperties(array $properties): self
    {
        return match (true) {
            isset($properties['in-reply-to']) => self::Reply,
            isset($properties['like-of']) => self::Like,
            isset($properties['repost-of']) => self::Repost,
            isset($properties['bookmark-of']) => self::Bookmark,
            isset($properties['rsvp']) => self::Rsvp,
            default => self::Mention,
        };
    }
}
