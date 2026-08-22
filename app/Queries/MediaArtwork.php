<?php

namespace App\Queries;

use App\Models\Media;

/**
 * The hero artwork for a media entry: backdrop, title logo and poster.
 *
 * An episode carries no artwork of its own, so it reads its show's. TMDB has
 * no still for an individual episode, which is why this falls back rather than
 * fetching.
 */
final class MediaArtwork
{
    /**
     * `logoIsTitle` is false for an episode, whose logo names the show rather
     * than the episode, so the page keeps its own heading as well.
     *
     * @return array{backdrop: ?string, logo: ?string, poster: ?string, logoIsTitle: bool}
     */
    public function __invoke(Media $media): array
    {
        $source = $media->optimisedUrl('backdrop') === null
            ? $media->series ?? $media
            : $media;

        return [
            'backdrop' => $source->optimisedUrl('backdrop'),
            'logo' => $source->optimisedUrl('logo'),
            'poster' => $source->optimisedUrl('cover'),
            'logoIsTitle' => $source === $media,
        ];
    }
}
