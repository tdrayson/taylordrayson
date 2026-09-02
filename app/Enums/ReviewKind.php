<?php

namespace App\Enums;

/**
 * The questions a photograph can be reviewed for. A dimension earns a case only
 * when its absence is ambiguous: subjects can be legitimately empty, alt text
 * cannot, so alt text is a filter with no case here.
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
