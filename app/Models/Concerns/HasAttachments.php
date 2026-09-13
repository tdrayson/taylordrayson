<?php

namespace App\Models\Concerns;

use App\Data\PhotoTagData;
use App\Enums\ReviewKind;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shared Media Library setup: the collections every timeline model can carry,
 * plus the `card` conversion feeds and grids render from.
 *
 * The stored original is already the optimised 1920px WebP, written by
 * {@see App\Support\OptimisingFileAdder} on the way in, so full-size renders
 * serve it directly rather than deriving a second copy of the same thing.
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
        // Images dropped inside a document, as opposed to the gallery.
        $this->addMediaCollection('body');
        $this->addMediaCollection('artwork')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')
            ->fit(Fit::Max, 640, 640)
            ->format('webp')
            ->quality(78)
            ->performOnCollections('cover', 'photos', 'artwork')
            ->withResponsiveImages();
    }

    /** The full-size render of a single-file collection. */
    public function optimisedUrl(string $collection): ?string
    {
        return $this->getFirstMedia($collection)?->getUrl();
    }

    /**
     * The entry's photos in display order, cover first, each with its id, card
     * source, srcset, lightbox render, authored alt/caption, route coordinate
     * where known, tagged subjects and review state.
     *
     * @return array<int, array{id: int, src: string, srcset: ?string, full: string, alt: ?string, caption: ?string, latitude: ?float, longitude: ?float, tags: list<array<string, mixed>>, reviewed: array<string, bool>}>
     */
    public function galleryPhotos(): array
    {
        /** @var Collection<int, Attachment> $photos */
        $photos = $this->getMedia('cover')->merge($this->getMedia('photos'));
        $photos->load('subjects');

        return $photos
            ->map(fn (Attachment $media): array => [
                'id' => $media->id,
                'src' => $media->getUrl('card'),
                'srcset' => $media->getSrcset('card') ?: null,
                'full' => $media->getUrl(),
                'alt' => $media->getCustomProperty('alt'),
                'caption' => $media->getCustomProperty('caption'),
                'latitude' => $media->getCustomProperty('latitude'),
                'longitude' => $media->getCustomProperty('longitude'),
                'tags' => $media->subjects->map(PhotoTagData::fromSubject(...))->all(),
                'reviewed' => collect(ReviewKind::cases())
                    ->mapWithKeys(fn (ReviewKind $kind): array => [$kind->value => $media->getCustomProperty($kind->property()) !== null])
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
