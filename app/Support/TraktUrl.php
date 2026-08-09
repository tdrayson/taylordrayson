<?php

namespace App\Support;

use App\Enums\MediaType;
use App\Enums\Source;
use App\Models\Media;

/**
 * Builds links back to Trakt.
 *
 * One place rather than three: the show URL was assembled inline in
 * SeriesShowData while the film and episode URLs lived on the Media model as
 * presentation logic, so the same host and path shapes were written out twice
 * and could drift apart.
 */
final class TraktUrl
{
    private const BASE = 'https://trakt.tv';

    /**
     * The Trakt page for a watch, or null when it did not come from Trakt or
     * carries no slug to link to.
     */
    public static function forMedia(Media $media): ?string
    {
        if ($media->source !== Source::Trakt->value) {
            return null;
        }

        return match ($media->type) {
            MediaType::Film => self::film($media->meta['ids']['slug'] ?? null),
            MediaType::TvEpisode => self::episode(
                $media->meta['show_slug'] ?? null,
                $media->meta['season'] ?? null,
                $media->meta['episode'] ?? null,
            ),
            default => null,
        };
    }

    public static function film(?string $slug): ?string
    {
        return $slug ? self::BASE."/movies/{$slug}" : null;
    }

    public static function show(?string $slug): ?string
    {
        return $slug ? self::BASE."/shows/{$slug}" : null;
    }

    /**
     * All three parts are required: a season or episode number missing would
     * otherwise produce a URL pointing at the wrong episode, or at none.
     */
    public static function episode(?string $showSlug, int|string|null $season, int|string|null $episode): ?string
    {
        if (! $showSlug || $season === null || $episode === null) {
            return null;
        }

        return self::BASE."/shows/{$showSlug}/seasons/{$season}/episodes/{$episode}";
    }
}
