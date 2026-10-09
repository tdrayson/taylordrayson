<?php

namespace App\Enums;

/** The attribute a `<meta>` tag names its key with. */
enum MetaAttribute: string
{
    case Name = 'name';
    case Property = 'property';
    case HttpEquiv = 'http-equiv';

    public function label(): string
    {
        return match ($this) {
            self::Name => 'Name',
            self::Property => 'Property',
            self::HttpEquiv => 'HTTP equivalent',
        };
    }
}
