<?php

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shared Media Library setup: a single `cover`, a `photos` gallery, and a single
 * generated `map`, plus an optimised, responsive `card` conversion for feeds.
 */
trait HasAttachments
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('photos');
        $this->addMediaCollection('map')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')
            ->fit(Fit::Max, 640, 640)
            ->format('webp')
            ->quality(78)
            ->performOnCollections('cover', 'photos')
            ->withResponsiveImages();
    }

    /**
     * The entry's photos in display order (cover first, then the gallery),
     * each with the optimised card source, its responsive srcset, and the
     * full-size original for the lightbox.
     *
     * @return array<int, array{src: string, srcset: ?string, full: string}>
     */
    public function galleryPhotos(): array
    {
        return $this->getMedia('cover')
            ->merge($this->getMedia('photos'))
            ->map(fn (Media $media): array => [
                'src' => $media->getUrl('card'),
                'srcset' => $media->getSrcset('card') ?: null,
                'full' => $media->getUrl(),
            ])
            ->values()
            ->all();
    }
}
