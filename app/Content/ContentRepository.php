<?php

namespace App\Content;

use Statamic\Facades\Entry;

class ContentRepository
{
    public function __construct(private readonly BardRenderer $bard) {}

    /** Find a page entry by slug; returns null when no entry exists. */
    public function page(string $slug): ?ContentEntry
    {
        $entry = Entry::query()
            ->where('collection', 'pages')
            ->where('slug', $slug)
            ->first();

        if ($entry === null) {
            return null;
        }

        return new ContentEntry($entry, $this->bard);
    }
}
