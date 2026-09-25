<?php

namespace App\Support;

use App\Data\PhotoTagData;
use App\Enums\ReviewKind;
use App\Models\Appearance;
use App\Models\Attachment;
use App\Models\Book;
use App\Models\Concerns\Timelineable;
use App\Models\Film;
use App\Models\TvEpisode;
use App\Presenters\PhotoCaption;
use App\Timeline\TypeRegistry;
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
    public const ENRICHMENT_MODELS = [Appearance::class, Film::class, TvEpisode::class, Book::class];

    /**
     * Dataset aliases (the morph column value) whose photos reach the gallery,
     * for a query that must filter before it can hydrate. Derived from the
     * timeline registry rather than listed, so this states the same rule
     * contributesPhotos() applies at runtime: every timeline type, minus the
     * enrichment art.
     *
     * @return list<string>
     */
    public static function includedModels(): array
    {
        return collect(TypeRegistry::all())
            ->pluck('model')
            ->reject(fn (string $model): bool => in_array($model, self::ENRICHMENT_MODELS, true))
            ->map(fn (string $model): string => (new $model)->getMorphClass())
            ->values()
            ->all();
    }

    /**
     * Whether a model's cover/photos are real photographs. Non-timeline models
     * (a TvShow poster, say) are excluded too, hence the Timelineable guard
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
        // Resolved once per entry, not once per photo: every photo an entry
        // owns shares its caption, date, accent and permalink.
        $caption = PhotoCaption::for($model);
        $media->loadMissing('subjects');

        return $media->map(function (Attachment $item) use ($caption): array {
            // Built once and reused: getSrcset() re-derives every conversion
            // URL, and the tile's dimensions are parsed back out of it.
            $srcset = $item->getSrcset('card');

            return [
                'id' => $item->id,
                ...self::dimensions($srcset),
                'src' => $item->getUrl('card'),
                'srcset' => $srcset ?: null,
                'full' => $item->getUrl(),
                'alt' => $item->getCustomProperty('alt'),
                // Deliberately the entry's title, not the photo's own caption:
                // across a wall of photos the useful label is where each came from.
                ...$caption,
                'tags' => $item->subjects->map(PhotoTagData::fromSubject(...))->all(),
                'reviewed' => collect(ReviewKind::cases())
                    ->mapWithKeys(fn (ReviewKind $kind): array => [$kind->value => $item->getCustomProperty($kind->property()) !== null])
                    ->all(),
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
