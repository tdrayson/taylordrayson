<?php

namespace App\Enums;

/**
 * A tab in the entry editor that a field can be placed on, beyond the type's
 * main tab. Case order is the tab order.
 */
enum EditorTab: string
{
    case Summary = 'summary';
    case Response = 'response';
    case Details = 'details';

    public function label(): string
    {
        return match ($this) {
            self::Summary => 'Summary',
            self::Response => 'Response',
            self::Details => 'Details',
        };
    }
}
