<?php

namespace App\Enums;

/**
 * The answer an RSVP gives.
 *
 * The four values post type discovery recognises. Anything else and the post
 * stops being an RSVP to a parser, so the set is not ours to extend.
 */
enum RsvpValue: string
{
    case Yes = 'yes';
    case No = 'no';
    case Maybe = 'maybe';
    case Interested = 'interested';

    public function label(): string
    {
        return match ($this) {
            self::Yes => 'Going',
            self::No => 'Not going',
            self::Maybe => 'Maybe',
            self::Interested => 'Interested',
        };
    }

    /**
     * How the context line says it. The answer is the verb: "Going to an event
     * on indieweb.org" reads, where "RSVP'd to Going an event" does not.
     */
    public function verb(): string
    {
        return match ($this) {
            self::Yes => 'Going to',
            self::No => 'Not going to',
            self::Maybe => 'Maybe going to',
            self::Interested => 'Interested in',
        };
    }

    /**
     * How a timeline card says it. The answer carries the verb, so an RSVP
     * needs no wording of its own beyond this.
     */
    public function sentence(): string
    {
        return match ($this) {
            self::Yes => 'I’m going to',
            self::No => 'I’m not going to',
            self::Maybe => 'I might go to',
            self::Interested => 'I’m interested in',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $value): array => ['value' => $value->value, 'label' => $value->label()],
            self::cases(),
        );
    }
}
