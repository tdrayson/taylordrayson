<?php

namespace App\Enums;

/**
 * What one of my posts is doing to somebody else's.
 *
 * A closed set the app defines, so unlike WebmentionKind this one is cast on
 * the model: nothing but the editor writes it.
 *
 * `bookmark` is deliberately absent. A bookmark is an object worth keeping,
 * with its own metadata and its own archive, so it is a type of its own; these
 * are gestures aimed at somebody else, carrying nothing but a target.
 */
enum ResponseKind: string
{
    case Reply = 'reply';
    case Like = 'like';
    case Repost = 'repost';
    case Rsvp = 'rsvp';

    /**
     * The microformats2 property the target link carries.
     *
     * An RSVP is an in-reply-to that also answers, which is why post type
     * discovery checks the answer first: the property alone cannot tell the two
     * apart.
     */
    public function property(): string
    {
        return match ($this) {
            self::Reply, self::Rsvp => 'in-reply-to',
            self::Like => 'like-of',
            self::Repost => 'repost-of',
        };
    }

    /** How a card says what I did, as a sentence out loud. */
    public function label(): string
    {
        return match ($this) {
            self::Reply => 'Replied to',
            self::Like => 'Liked',
            self::Repost => 'Reposted',
            self::Rsvp => 'RSVP’d to',
        };
    }

    /**
     * How a timeline card says it, as a first-person sentence, matching the
     * other generated titles ("I slept for 7h 55m", "I walked 0.7 mi").
     *
     * An RSVP has none of its own: its answer supplies the verb, so
     * RsvpValue::sentence() speaks for it.
     */
    public function sentence(): ?string
    {
        return match ($this) {
            self::Reply => 'I replied to',
            self::Like => 'I liked',
            self::Repost => 'I reposted',
            self::Rsvp => null,
        };
    }

    /** A gesture has no words of its own: the target is the whole post. */
    public function isGesture(): bool
    {
        return $this !== self::Reply;
    }

    /**
     * The choices the editor offers.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $kind): array => ['value' => $kind->value, 'label' => $kind->label()],
            self::cases(),
        );
    }
}
