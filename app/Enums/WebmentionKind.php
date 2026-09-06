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

    /**
     * Ours, not theirs: a reacji arrives as an ordinary in-reply-to and is
     * only distinguishable by its body being a single emoji, so the narrowing
     * is done here and recorded under a name of our own.
     */
    case Reacji = 'reacji';

    public function label(): string
    {
        return match ($this) {
            self::Reacji => 'Reaction',
            self::Reply => 'Reply',
            self::Like => 'Like',
            self::Repost => 'Repost',
            self::Bookmark => 'Bookmark',
            self::Rsvp => 'RSVP',
            self::Mention => 'Mention',
        };
    }
}
