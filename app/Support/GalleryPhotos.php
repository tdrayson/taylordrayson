<?php

namespace App\Support;

use App\Models\Concerns\Timelineable;
use App\Presenters\CardPresenter;
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
     * Card pixel dimensions parsed from the responsive filenames
     * (`…_card_480_640.webp`), so a masonry tile can reserve its aspect ratio.
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
