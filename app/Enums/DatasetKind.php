<?php

namespace App\Enums;

/**
 * The area of life a dataset belongs to, used to group datasets on the site.
 */
enum DatasetKind: string
{
    case Writing = 'writing';
    case Watching = 'watching';
    case Travel = 'travel';
    case Health = 'health';
    case GoingOut = 'going-out';
    case Speaking = 'speaking';

    public function label(): string
    {
        return match ($this) {
            self::Writing => 'Writing',
            self::Watching => 'Watching & reading',
            self::Travel => 'Travel',
            self::Health => 'Life & health',
            self::GoingOut => 'Going out',
            self::Speaking => 'Speaking',
        };
    }

    /** One friendly line for the feed picker. */
    public function description(): string
    {
        return match ($this) {
            self::Writing => "Notes, articles, and the projects I'm tinkering with.",
            self::Watching => 'Films, telly, and books.',
            self::Travel => "Flights, places I've been, and petrol stops.",
            self::Health => "Workouts, sleep, and what I've been eating.",
            self::GoingOut => "Gigs, shows, and things I've turned up to.",
            self::Speaking => 'The podcast, talks, and interviews.',
        };
    }
}
