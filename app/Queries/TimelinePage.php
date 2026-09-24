<?php

namespace App\Queries;

use App\Data\TimelinePageData;
use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * One page of the timeline, anchored to an entry's instant rather than an
 * offset, so a shared link does not drift as new entries arrive.
 */
final class TimelinePage
{
    public const PER_PAGE = 50;

    private const INSTANT = 'COALESCE(occurred_utc, occurred_at)';

    /**
     * The page at the cursor, newest first.
     *
     * @param  string|null  $before  Instant: start at the newest entry older than this.
     * @param  string|null  $after  Instant: start at the oldest entry newer than this, filling forward.
     */
    public function __invoke(?string $before = null, ?string $after = null): TimelinePageData
    {
        $forward = $after !== null;
        $taken = $this->take($forward ? $after : $before, $forward);

        if ($taken->isEmpty()) {
            return new TimelinePageData(collect());
        }

        $entries = $forward ? $taken->reverse()->values() : $taken;
        $newest = $this->instant($entries->first());
        $oldest = $this->instant($entries->last());

        return new TimelinePageData(
            entries: $entries,
            olderThan: $this->exists($oldest, older: true) ? str_replace(' ', 'T', $oldest) : null,
            newerThan: $this->exists($newest, older: false) ? str_replace(' ', 'T', $newest) : null,
        );
    }

    /**
     * A page of entries walking away from the cursor.
     *
     * @return Collection<int, TimelineEntry>
     */
    private function take(?string $cursor, bool $forward): Collection
    {
        $query = TimelineEntry::query()
            ->withCardRelations()
            ->when($cursor !== null, fn (Builder $query) => $query->whereRaw(self::INSTANT.($forward ? ' > ?' : ' < ?'), [$cursor]))
            ->orderByInstant($forward ? 'asc' : 'desc');

        $entries = $query->clone()->limit(self::PER_PAGE)->get();

        if ($entries->count() < self::PER_PAGE) {
            return $entries;
        }

        // A time cursor cannot point between entries sharing an instant, so the page takes them all.
        $ties = $query->clone()
            ->whereRaw(self::INSTANT.' = ?', [$this->instant($entries->last())])
            ->whereKeyNot($entries->modelKeys())
            ->get();

        return $entries->concat($ties);
    }

    /** Whether any entry lies past an instant, which is what decides the links. */
    private function exists(string $instant, bool $older): bool
    {
        return TimelineEntry::query()
            ->whereRaw(self::INSTANT.($older ? ' < ?' : ' > ?'), [$instant])
            ->exists();
    }

    /** The raw instant the feed is ordered by, as stored. */
    private function instant(TimelineEntry $entry): string
    {
        return $entry->getRawOriginal('occurred_utc') ?? $entry->getRawOriginal('occurred_at');
    }
}
