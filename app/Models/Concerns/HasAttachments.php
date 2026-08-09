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

    /**
     * Every stored image, at the largest size anything on the site displays.
     *
     * Nothing here is over 1920 to begin with (maps are 1600x1000, photos
     * mostly 1440x1920), so the resize is a backstop and the saving is almost
     * entirely the format: a 436KB map PNG becomes 21KB of WebP, and a 497KB
     * photo becomes 310KB. Serving this in place of the original takes the
     * library from ~3.5GB to roughly 400MB.
     */
    private const FULL_DIMENSION = 1920;

    /**
     * Formats left exactly as uploaded.
     *
     * SVG is vector: rasterising it to 1920 would be a downgrade, and the six
     * stored are ~20KB in total. GIF is skipped because Imagick would flatten
     * an animated one to a single frame, which is a silent loss of the thing
     * that made it a GIF.
     *
     * @var array<int, string>
     */
    private const UNCONVERTED_TYPES = ['image/svg+xml', 'image/gif'];

    public function registerMediaConversions(?Media $media = null): void
    {
        if (in_array($media?->mime_type, self::UNCONVERTED_TYPES, true)) {
            return;
        }

        $this->addMediaConversion('card')
            ->fit(Fit::Max, 640, 640)
            ->format('webp')
            ->quality(78)
            // Not `audio`: the conversion pipeline is an image one, and pointing
            // it at an MP3 would have every mirrored episode fail a conversion
            // it was never going to produce.
            ->performOnCollections('cover', 'photos', 'artwork')
            ->withResponsiveImages();

        // No responsive variants: this is the one full-size render, shown on its
        // own in a lightbox or behind a card, never picked from a srcset.
        $this->addMediaConversion('full')
            ->fit(Fit::Max, self::FULL_DIMENSION, self::FULL_DIMENSION)
            ->format('webp')
            ->quality(80)
            ->performOnCollections('cover', 'photos', 'artwork', 'map', 'map_dark', 'backdrop', 'logo');
    }

    /**
     * The optimised render of a single-file collection, falling back to the
     * stored original.
     *
     * The fallback carries real weight while the back-fill runs and for the
     * formats above that never get a conversion, so callers can move to this
     * without waiting for every conversion to exist.
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
                // The optimised render, not the import. A phone photo went to
                // the lightbox as its original multi-megabyte JPEG (or, for the
                // 28 stored HEICs, as a file most browsers cannot display at
                // all); this serves a 1920 WebP instead.
                'full' => $media->hasGeneratedConversion('full') ? $media->getUrl('full') : $media->getUrl(),
                'latitude' => $media->getCustomProperty('latitude'),
                'longitude' => $media->getCustomProperty('longitude'),
            ])
            ->values()
            ->all();
    }
}
