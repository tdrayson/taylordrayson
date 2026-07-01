<?php

namespace App\Content;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Statamic\Facades\Entry;

/**
 * Central read-model seam for Statamic content.
 *
 * Provides typed Collections of ContentEntry consumed by the timeline,
 * archives, entry-detail, feeds, search, and OG tasks (Tasks 10-15).
 * All methods exclude draft entries unless explicitly noted.
 */
class ContentRepository
{
    public function __construct(private readonly BardRenderer $bard) {}

    // -------------------------------------------------------------------------
    // Collection accessors
    // -------------------------------------------------------------------------

    /**
     * All published articles, ordered by date descending (newest first).
     *
     * @return Collection<int, ContentEntry>
     */
    public function articles(): Collection
    {
        return Entry::query()
            ->where('collection', 'articles')
            ->whereStatus('published')
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($entry) => new ContentEntry($entry, $this->bard))
            ->values();
    }

    /**
     * All published notes, ordered by date descending.
     *
     * @return Collection<int, ContentEntry>
     */
    public function notes(): Collection
    {
        return Entry::query()
            ->where('collection', 'notes')
            ->whereStatus('published')
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($entry) => new ContentEntry($entry, $this->bard))
            ->values();
    }

    /**
     * All published articles and notes combined, ordered by date descending.
     *
     * @return Collection<int, ContentEntry>
     */
    public function all(): Collection
    {
        return $this->articles()
            ->merge($this->notes())
            ->sortByDesc(fn (ContentEntry $e) => $e->occurredAt()->timestamp)
            ->values();
    }

    // -------------------------------------------------------------------------
    // Point lookup
    // -------------------------------------------------------------------------

    /**
     * Find a single published entry by type ('article'|'note') and slug.
     * Returns null when no matching entry exists.
     */
    public function findByTypeAndSlug(string $type, string $slug): ?ContentEntry
    {
        $collection = match ($type) {
            'article' => 'articles',
            'note' => 'notes',
            'page' => 'pages',
            default => $type,
        };

        $entry = Entry::query()
            ->where('collection', $collection)
            ->where('slug', $slug)
            ->whereStatus('published')
            ->first();

        if ($entry === null) {
            return null;
        }

        return new ContentEntry($entry, $this->bard);
    }

    // -------------------------------------------------------------------------
    // Date-range query (consumed by timeline merge in Task 10)
    // -------------------------------------------------------------------------

    /**
     * All published articles and notes whose entry date falls within
     * [$oldest, $newest] inclusive. Both parameters are compared at
     * day granularity (date string comparison).
     *
     * @return Collection<int, ContentEntry>
     */
    public function betweenDates(CarbonInterface $newest, CarbonInterface $oldest): Collection
    {
        $newestDate = $newest->toDateString();
        $oldestDate = $oldest->toDateString();

        return $this->all()->filter(function (ContentEntry $entry) use ($newestDate, $oldestDate): bool {
            $entryDate = $entry->occurredAt()->toDateString();

            return $entryDate >= $oldestDate && $entryDate <= $newestDate;
        })->values();
    }

    // -------------------------------------------------------------------------
    // Page lookup (pre-existing, retained)
    // -------------------------------------------------------------------------

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
