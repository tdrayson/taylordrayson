<?php

namespace App\Enums;

/** Where in a document a dynamic tag may legally be placed. */
enum Placement: string
{
    case Inline = 'inline';
    case Href = 'href';
    case Image = 'image';

    public function label(): string
    {
        return match ($this) {
            self::Inline => 'In a sentence',
            self::Href => 'A link target',
            self::Image => 'An image source',
        };
    }
}
