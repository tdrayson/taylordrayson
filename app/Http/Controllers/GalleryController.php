<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Attachment;
use App\Support\OgMeta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    /**
     * The photo gallery: every real photo (the cover + photos collections) across
     * every entry, newest first. Generated maps and derived video thumbnails
     * (appearance covers) are deliberately excluded.
     */
    public function index(): Response
    {
        $attachments = Attachment::query()
            ->whereIn('collection_name', ['cover', 'photos'])
            ->whereNot('model_type', Appearance::class)
            ->with(['model' => fn (MorphTo $morphTo) => $morphTo->morphWith([Activity::class => ['media']])])
            ->get();

        $photos = $attachments
            ->filter(fn (Attachment $attachment): bool => $attachment->model !== null)
            ->groupBy(fn (Attachment $attachment): string => $attachment->model_type.':'.$attachment->model_id)
            ->flatMap(fn (Collection $group): array => $this->photosForModel($group))
            ->sortByDesc('sort')
            ->values()
            ->map(fn (array $photo): array => [
                'src' => $photo['src'],
                'srcset' => $photo['srcset'],
                'full' => $photo['full'],
                'width' => $photo['width'],
                'height' => $photo['height'],
                'caption' => $photo['caption'],
                'date' => $photo['sort']?->format('j M Y'),
                'accent' => $photo['accent'],
                'url' => $photo['url'],
            ])
            ->all();

        return Inertia::render('Photos', [
            'og' => OgMeta::gallery(),
            'photos' => $photos,
        ]);
    }

    /**
     * Shape every photo on a single owning model, sharing one card lookup.
     *
     * @param  Collection<int, Attachment>  $group  Attachments for one model.
     * @return array<int, array<string, mixed>>
     */
    private function photosForModel(Collection $group): array
    {
        $model = $group->first()->model;
        $card = $model->card();

        return $group->map(fn (Attachment $attachment): array => [
            ...$this->dimensions($attachment),
            'src' => $attachment->getUrl('card'),
            'srcset' => $attachment->getSrcset('card') ?: null,
            'full' => $attachment->getUrl(),
            'caption' => $card['title'],
            'accent' => $card['accent'],
            'url' => $model->url(),
            'sort' => $model->occurred_at,
        ])->all();
    }

    /**
     * The card-conversion pixel dimensions, parsed from the responsive-image
     * filenames (e.g. `…_card_480_640.webp`), so the masonry tile can reserve its
     * aspect ratio and avoid layout shift. Null when no responsive set exists.
     *
     * @return array{width: int|null, height: int|null}
     */
    private function dimensions(Attachment $attachment): array
    {
        $srcset = $attachment->getSrcset('card');

        if ($srcset !== '' && preg_match('/_(\d+)_(\d+)\.(?:webp|jpe?g|png)/', $srcset, $matches) === 1) {
            return ['width' => (int) $matches[1], 'height' => (int) $matches[2]];
        }

        return ['width' => null, 'height' => null];
    }
}
