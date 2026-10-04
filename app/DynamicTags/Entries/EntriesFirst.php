<?php

namespace App\DynamicTags\Entries;

/** When a type first appeared on the timeline. */
class EntriesFirst extends EntriesDate
{
    public function name(): string
    {
        return 'entries.first';
    }

    public function label(): string
    {
        return 'First entry';
    }

    protected function direction(): string
    {
        return 'asc';
    }
}
