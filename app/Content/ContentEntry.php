<?php

namespace App\Content;

use Statamic\Entries\Entry;

/** Wraps a Statamic Entry from the pages collection into a typed seam. */
class ContentEntry
{
    public function __construct(
        private readonly Entry $entry,
        private readonly BardRenderer $bard,
    ) {}

    public function title(): string
    {
        return (string) $this->entry->get('title');
    }

    public function excerpt(): ?string
    {
        $value = $this->entry->get('excerpt');

        return $value !== null ? (string) $value : null;
    }

    public function bodyHtml(): string
    {
        return $this->bard->toHtml($this->entry->augmentedValue('content'));
    }

    public function isDraft(): bool
    {
        return ! $this->entry->published();
    }

    public function slug(): string
    {
        return (string) $this->entry->slug();
    }
}
