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
}
