<?php

namespace App\Support;

use App\Enums\MediaType;
use App\Models\Media;

/**
 * The show a TV episode belongs to, since a Media row's `title` is the episode's.
 * The `series` relation wins over the denormalised `meta.show_title` because it
 * is the record that can be corrected; it is eager-loaded to avoid an N+1.
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
        return self::clean($media->series?->title) ?? self::clean($media->meta['show_title'] ?? null);
    }

    private static function clean(?string $value): ?string
    {
        return trim((string) $value) ?: null;
    }
}
