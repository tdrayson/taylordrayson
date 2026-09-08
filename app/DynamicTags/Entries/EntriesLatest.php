<?php

namespace App\DynamicTags\Entries;

/** When a type last appeared on the timeline. */
class EntriesLatest extends EntriesFirst
{
    protected string $direction = 'desc';

    public function name(): string
    {
        return 'entries.latest';
    }

    public function label(): string
    {
        return 'Latest entry';
    }
}
