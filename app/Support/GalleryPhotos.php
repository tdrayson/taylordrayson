<?php

namespace App\Support;

use App\Data\PhotoTagData;
use App\Enums\ReviewKind;
use App\Models\Appearance;
use App\Models\Attachment;
use App\Models\Concerns\Timelineable;
use App\Models\Media as MediaEntry;
use App\Presenters\CardPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
     * @param  Collection<int, Attachment>  $media  Cover + photos media, in display order.
     * @return array<int, array<string, mixed>>
     */
    public static function shape(Model&Timelineable $model, Collection $media): array
    {
        $card = CardPresenter::for($model);
        $media->loadMissing('subjects');

        return $media->map(fn (Attachment $item): array => [
            'id' => $item->id,
            ...self::dimensions($item),
            'src' => $item->getUrl('card'),
            'srcset' => $item->getSrcset('card') ?: null,
            'full' => $item->getUrl(),
            'alt' => $item->getCustomProperty('alt'),
            // `caption` here is deliberately the entry's title, not the photo's
            // own: across a wall of photos from everywhere, the useful label is
            // which entry each came from. This is the opposite of
            // HasAttachments::galleryPhotos(), where the reader is already on
            // the entry and the useful label is what the picture itself shows.
            // Do not "fix" this into the photo's own caption.
            'caption' => $card->title,
            'date' => $card->occurredAt->format('j M Y'),
            'accent' => $card->accent,
            'url' => $model->url(),
            'tags' => $item->subjects->map(PhotoTagData::fromSubject(...))->all(),
            'reviewed' => collect(ReviewKind::cases())
                ->mapWithKeys(fn (ReviewKind $kind): array => [$kind->value => $item->getCustomProperty($kind->property()) !== null])
                ->all(),
        ])->values()->all();
    }

    /**
     * Card pixel dimensions parsed from the responsive filenames
     * (`…_card_480_640.webp`), so a masonry tile can reserve its aspect ratio.
     *
     * @return array{width: int|null, height: int|null}
     */
    private static function dimensions(Attachment $media): array
    {
        $srcset = $media->getSrcset('card');

        if ($srcset !== '' && preg_match('/_(\d+)_(\d+)\.(?:webp|jpe?g|png)/', $srcset, $matches) === 1) {
            return ['width' => (int) $matches[1], 'height' => (int) $matches[2]];
        }

        return ['width' => null, 'height' => null];
    }

    /**
     * Model types currently holding cover/photos media that contributesPhotos()
     * would keep, found via is_subclass_of() rather than loading rows, so a raw
     * count (the /photos filter bar) can match PhotoStream's population without
     * hydrating a single model.
     *
     * @return list<class-string>
     */
    public static function contributingModelTypes(): array
    {
        return Attachment::query()
            ->whereIn('collection_name', ['cover', 'photos'])
            ->distinct()
            ->pluck('model_type')
            ->filter(fn (string $type): bool => is_subclass_of($type, Timelineable::class) && ! in_array($type, self::ENRICHMENT_MODELS, true))
            ->values()
            ->all();
    }
}
