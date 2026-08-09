<?php

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shared Media Library setup: a single `cover`, a `photos` gallery, a single
 * generated `map` (with a `map_dark` twin for dark mode), single-file
 * `backdrop`/`logo` collections (TMDB enrichment art), a square `artwork`
 * collection and an `audio` collection (mirrored podcast episodes), plus an
 * optimised, responsive `card` conversion for feeds.
 */
trait HasAttachments
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('photos');
        $this->addMediaCollection('map')->singleFile();
        $this->addMediaCollection('map_dark')->singleFile();
        $this->addMediaCollection('backdrop')->singleFile();
        $this->addMediaCollection('logo')->singleFile();
        $this->addMediaCollection('artwork')->singleFile();
        $this->addMediaCollection('audio')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')
            ->fit(Fit::Max, 640, 640)
            ->format('webp')
            ->quality(78)
            // Not `audio`: the conversion pipeline is an image one, and pointing
            // it at an MP3 would have every mirrored episode fail a conversion
            // it was never going to produce.
            ->performOnCollections('cover', 'photos', 'artwork')
            ->withResponsiveImages();
    }

    /**
     * The entry's photos in display order (cover first, then the gallery), each
     * with the optimised card source, its responsive srcset, the full-size
     * original for the lightbox, and the route coordinate where known.
     *
     * @return array<int, array{src: string, srcset: ?string, full: string, latitude: ?float, longitude: ?float}>
     */
    public function galleryPhotos(): array
    {
        return $this->getMedia('cover')
            ->merge($this->getMedia('photos'))
            ->map(fn (Media $media): array => [
                'src' => $media->getUrl('card'),
                'srcset' => $media->getSrcset('card') ?: null,
                'full' => $media->getUrl(),
                'latitude' => $media->getCustomProperty('latitude'),
                'longitude' => $media->getCustomProperty('longitude'),
            ])
            ->values()
            ->all();
    }
}
