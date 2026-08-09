<?php

namespace App\Support;

use App\Enums\MediaType;
use App\Models\Media;

/**
 * The show a TV episode belongs to.
 *
 * A Media row's `title` is the episode's, so anything naming the show has to
 * resolve it separately. Two places did, in opposite orders: the single-episode
 * card read the denormalised `meta.show_title` first, the collapsed binge card
 * read the `series` relation first. Identical while the two agree, and two
 * different names on the same page once a show is renamed.
 *
 * The relation wins, because it is the record that can be corrected. That is
 * only affordable because `series` is eager-loaded with the feed
 * (TimelineEntry::cardRelations); reading it per row otherwise would be an N+1
 * across the timeline.
 */
final class ShowTitle
{
    /** Null for anything that is not an episode, or whose show cannot be named. */
    public static function for(Media $media): ?string
    {
        if ($media->type !== MediaType::TvEpisode) {
            return null;
        }

        // Trimmed rather than null-coalesced: a blank stored title is not a
        // title, and `??` would accept it and leave the card headed by nothing.
        // MediaMeta already applies that rule to the stored fallback.
        return self::clean($media->series?->title) ?? $media->meta->showTitle;
    }

    private static function clean(?string $value): ?string
    {
        return trim((string) $value) ?: null;
    }
}
