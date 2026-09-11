<?php

namespace App\DynamicTags\Entries;

/** When a type last appeared on the timeline. */
class EntriesLatest extends EntriesDate
{
    public function name(): string
    {
        return 'entries.latest';
    }

    public function label(): string
    {
        return 'Latest entry';
    }

    protected function direction(): string
    {
        return 'desc';
    }
}
