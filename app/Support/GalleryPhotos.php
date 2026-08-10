<?php

namespace App\Support;

use App\Models\Appearance;
use App\Models\Concerns\Timelineable;
use App\Models\Media as MediaEntry;
use App\Presenters\CardPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shapes a Timelineable model's photos (cover + gallery, in that order) into
 * the payload shared by the photo gallery and any per-period photo strip:
 * optimised src, responsive srcset, full-size original, pixel dimensions
 * (parsed from the responsive filenames), caption/accent from the card, and
 * the entry link.
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
        $card = CardPresenter::for($model);

        return $media->map(fn (Media $item): array => [
            ...self::dimensions($item),
            'src' => $item->getUrl('card'),
            'srcset' => $item->getSrcset('card') ?: null,
            'full' => $item->getUrl(),
            'caption' => $card->title,
            'date' => $card->occurredAt->format('j M Y'),
            'accent' => $card->accent,
            'url' => $model->url(),
        ])->values()->all();
    }

    /**
     * The card-conversion pixel dimensions, parsed from the responsive-image
     * filenames (e.g. `…_card_480_640.webp`), so a masonry tile can reserve its
     * aspect ratio and avoid layout shift. Null when no responsive set exists.
     *
     * @return array{width: int|null, height: int|null}
     */
    private static function dimensions(Media $media): array
    {
        $srcset = $media->getSrcset('card');

        if ($srcset !== '' && preg_match('/_(\d+)_(\d+)\.(?:webp|jpe?g|png)/', $srcset, $matches) === 1) {
            return ['width' => (int) $matches[1], 'height' => (int) $matches[2]];
        }

        return ['width' => null, 'height' => null];
    }
}
