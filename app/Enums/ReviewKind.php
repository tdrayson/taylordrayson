<?php

namespace App\Enums;

/**
 * Review dimensions for photographs. A case appears only when absence is
 * ambiguous (subjects can be empty; alt text cannot, so no case for it).
 */
enum ReviewKind: string
{
    case Subjects = 'subjects';

    public function label(): string
    {
        return match ($this) {
            self::Subjects => 'Needs tagging',
        };
    }

    /** The dot path inside an attachment's custom_properties. */
    public function property(): string
    {
        return "reviewed.{$this->value}";
    }
}
