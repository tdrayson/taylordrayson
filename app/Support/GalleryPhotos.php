<?php

namespace App\Support;

use App\Models\Appearance;
use App\Models\Concerns\Timelineable;
use App\Models\Media as MediaEntry;
use App\Models\Page;
use App\Models\Series;
use App\Presenters\PhotoCaption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shapes a Timelineable model's photos, cover first, into the payload shared by
 * the photo gallery and any per-period photo strip.
 */
class GalleryPhotos
{
    /**
     * Models whose cover/photos collections hold enrichment art rather than
     * photographs: appearance thumbnails derived from video, and film/TV/book
     * posters fetched from TMDB.
     *
     * @var list<class-string>
     */
    public const ENRICHMENT_MODELS = [Appearance::class, MediaEntry::class];

    /**
     * Every model type the gallery skips, as a list a query can filter on. The
     * runtime guard is contributesPhotos(); this is the same rule stated ahead
     * of hydration, so a count and the stream itself cannot disagree.
     *
     * @return list<class-string>
     */
    public static function excludedModels(): array
    {
        return [...self::ENRICHMENT_MODELS, Page::class, Series::class];
    }

    /**
     * Whether a model's cover/photos are real photographs. Non-timeline models
     * (a Series poster, say) are excluded too, hence the Timelineable guard
     * rather than a null check.
     */
    public static function contributesPhotos(?Model $model): bool
    {
        return $model instanceof Timelineable
            && ! in_array($model::class, self::ENRICHMENT_MODELS, true);
    }

    /**
     * @param  Collection<int, Media>  $media  Cover + photos media, in display order.
     * @return array<int, array<string, mixed>>
     */
    public static function shape(Model&Timelineable $model, Collection $media): array
    {
        // Resolved once per entry, not once per photo: every photo an entry
        // owns shares its caption, date, accent and permalink.
        $caption = PhotoCaption::for($model);

        return $media->map(function (Media $item) use ($caption): array {
            // Built once and reused: getSrcset() re-derives every conversion
            // URL, and the tile's dimensions are parsed back out of it.
            $srcset = $item->getSrcset('card');

            return [
                ...self::dimensions($srcset),
                'src' => $item->getUrl('card'),
                'srcset' => $srcset ?: null,
                'full' => $item->getUrl(),
                ...$caption,
            ];
        })->values()->all();
    }

    /**
     * Card pixel dimensions parsed from the responsive filenames
     * (`…_card_480_640.webp`), so a masonry tile can reserve its aspect ratio.
     *
     * @return array{width: int|null, height: int|null}
     */
    private static function dimensions(string $srcset): array
    {
        if ($srcset !== '' && preg_match('/_(\d+)_(\d+)\.(?:webp|jpe?g|png)/', $srcset, $matches) === 1) {
            return ['width' => (int) $matches[1], 'height' => (int) $matches[2]];
        }

        return ['width' => null, 'height' => null];
    }
}
