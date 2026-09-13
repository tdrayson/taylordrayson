<?php

namespace App\Support;

use App\Enums\Source;
use App\Models\Film;
use App\Models\TvEpisode;

/**
 * Builds links back to Trakt.
 *
 * One place rather than three: the show URL was assembled inline in
 * TvShowData while the film and episode URLs lived on their models as
 * presentation logic, so the same host and path shapes were written out twice
 * and could drift apart.
 */
final class TraktUrl
{
    private const BASE = 'https://trakt.tv';

    /** The Trakt page for a watched film, or null when it did not come from Trakt or carries no slug to link to. */
    public static function forFilm(Film $film): ?string
    {
        if ($film->source !== Source::Trakt->value) {
            return null;
        }

        return self::film($film->meta->ids->slug);
    }

    /** The Trakt page for a watched episode, or null when it did not come from Trakt or carries no slug to link to. */
    public static function forEpisode(TvEpisode $episode): ?string
    {
        if ($episode->source !== Source::Trakt->value) {
            return null;
        }

        return self::episode($episode->meta->showSlug, $episode->meta->season, $episode->meta->episode);
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
    public static function episode(?string $showSlug, ?int $season, ?int $episode): ?string
    {
        if (! $showSlug || $season === null || $episode === null) {
            return null;
        }

        return self::BASE."/shows/{$showSlug}/seasons/{$season}/episodes/{$episode}";
    }
}
