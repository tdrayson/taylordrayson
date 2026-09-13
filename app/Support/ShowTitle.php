<?php

namespace App\Support;

use App\Models\TvEpisode;

/**
 * The show an episode belongs to, since a TvEpisode row's `title` is the episode's.
 * The `tvShow` relation wins over the denormalised `meta.show_title` because it
 * is the record that can be corrected; it is eager-loaded to avoid an N+1.
 */
final class ShowTitle
{
    /** Null when the show cannot be named. */
    public static function for(TvEpisode $episode): ?string
    {
        // Trimmed rather than null-coalesced: a blank stored title is not a
        // title, and `??` would accept it and leave the card headed by nothing.
        // TvEpisodeMeta already applies that rule to the stored fallback.
        return self::clean($episode->tvShow?->title) ?? $episode->meta->showTitle;
    }

    private static function clean(?string $value): ?string
    {
        return trim((string) $value) ?: null;
    }
}
