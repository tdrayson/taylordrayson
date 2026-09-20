<?php

namespace App\Enums;

/** What happened to the object a Strava webhook event names. */
enum StravaAspect: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Created',
            self::Update => 'Updated',
            self::Delete => 'Deleted',
        };
    }
}
