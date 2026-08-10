<?php

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shared Media Library setup: the collections every timeline model can carry,
 * plus the `card` and `full` conversions feeds and lightboxes render from.
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

    /** The largest size anything on the site displays. */
    private const FULL_DIMENSION = 1920;

    /**
     * Formats left exactly as uploaded: rasterising vector SVG is a downgrade,
     * and Imagick flattens an animated GIF to a single frame.
     *
     * @var array<int, string>
     */
    private const UNCONVERTED_TYPES = ['image/svg+xml', 'image/gif'];

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')
            ->fit(Fit::Max, 640, 640)
            ->format('webp')
            ->quality(78)
            // Not `audio`: an image conversion pointed at an MP3 fails every time.
            ->performOnCollections('cover', 'photos', 'artwork')
            ->withResponsiveImages();

        // Returning before `card` is registered would break getUrl('card'), which
        // resolves against registered conversions rather than generated files.
        if (in_array($media?->mime_type, self::UNCONVERTED_TYPES, true)) {
            return;
        }

        // No responsive variants: shown on its own, never picked from a srcset.
        $this->addMediaConversion('full')
            ->fit(Fit::Max, self::FULL_DIMENSION, self::FULL_DIMENSION)
            ->format('webp')
            ->quality(80)
            ->performOnCollections('cover', 'photos', 'artwork', 'map', 'map_dark', 'backdrop', 'logo');
    }

    /**
     * The optimised render of a single-file collection, falling back to the
     * stored original where no conversion exists.
     */
    public function optimisedUrl(string $collection): ?string
    {
        $media = $this->getFirstMedia($collection);

        if ($media === null) {
            return null;
        }

        return $media->hasGeneratedConversion('full') ? $media->getUrl('full') : $media->getUrl();
    }

    /**
     * The entry's photos in display order, cover first, each with its card
     * source, srcset, lightbox render and route coordinate where known.
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
                // The optimised render, not the import: originals are multi-megabyte
                // JPEGs, and the stored HEICs most browsers cannot display at all.
                'full' => $media->hasGeneratedConversion('full') ? $media->getUrl('full') : $media->getUrl(),
                'latitude' => $media->getCustomProperty('latitude'),
                'longitude' => $media->getCustomProperty('longitude'),
            ])
            ->values()
            ->all();
    }
}
