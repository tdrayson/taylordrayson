<?php

namespace App\Support;

use App\Models\TimelineEntry;
use App\Queries\InteractionsForFeed;
use Illuminate\Support\Collection;
use Inertia\DeferProp;
use Inertia\Inertia;

/**
 * The `interactions` prop every page of feed cards sends, keyed `type:id`.
 *
 * Deferred rather than resolved: the counts are visitor-specific and would keep
 * the feed out of any page-wide cache, and the cards read fine without them.
 */
final class FeedInteractions
{
    /**
     * Reaction and response counts for the entries a page of cards was built from.
     *
     * @param  Collection<int, TimelineEntry>  $entries
     * @return DeferProp|array<string, mixed> An empty array for a page with no cards, which spares it the follow-up request.
     */
    public static function defer(Collection $entries): DeferProp|array
    {
        if ($entries->isEmpty()) {
            return [];
        }

        return Inertia::defer(fn (): array => app(InteractionsForFeed::class)(
            $entries->map(fn (TimelineEntry $entry) => $entry->entry)->filter()->values(),
            request(),
        ));
    }
}
