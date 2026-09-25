<?php

namespace App\Enums;

enum ActivityLevel: int
{
    case None = 0;
    case Light = 1;
    case Busy = 2;
    case Full = 3;

    /**
     * The shade for a day with this many timeline entries.
     */
    public static function fromCount(int $count): self
    {
        return match (true) {
            $count <= 0 => self::None,
            $count <= 2 => self::Light,
            $count <= 4 => self::Busy,
            default => self::Full,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::None => 'Nothing logged',
            self::Light => 'Light day',
            self::Busy => 'Busy day',
            self::Full => 'Full day',
        };
    }
}
