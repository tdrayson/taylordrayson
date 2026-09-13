<?php

namespace App\Enums;

/** Where a project is in its life, independent of whether it is published. */
enum ProjectStage: string
{
    case Active = 'active';
    case Maintained = 'maintained';
    case OnHold = 'on_hold';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Maintained => 'Maintained',
            self::OnHold => 'On hold',
            self::Archived => 'Archived',
        };
    }
}
