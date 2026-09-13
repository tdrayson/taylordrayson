<?php

namespace App\Queries;

use App\Models\Book;
use App\Models\Episode;
use App\Models\Film;

/**
 * The hero artwork for a film, episode or book entry: backdrop, title logo and
 * poster.
 *
 * An episode carries no artwork of its own, so it reads its show's. TMDB has
 * no still for an individual episode, which is why this falls back rather than
 * fetching.
 */
final class EntryArtwork
{
    /**
     * `logoIsTitle` is false for an episode, whose logo names the show rather
     * than the episode, so the page keeps its own heading as well.
     *
     * @return array{backdrop: ?string, logo: ?string, poster: ?string, logoIsTitle: bool}
     */
    public function __invoke(Film|Episode|Book $media): array
    {
        $fallback = $media instanceof Episode ? $media->series : null;
        $source = $media->optimisedUrl('backdrop') === null ? ($fallback ?? $media) : $media;

        return [
            'backdrop' => $source->optimisedUrl('backdrop'),
            'logo' => $source->optimisedUrl('logo'),
            'poster' => $source->optimisedUrl('cover'),
            'logoIsTitle' => $source === $media,
        ];
    }
}
