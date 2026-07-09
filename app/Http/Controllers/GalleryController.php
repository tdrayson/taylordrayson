<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Attachment;
use App\Support\GalleryPhotos;
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
            ->map(fn (array $photo): array => collect($photo)->except('sort')->all())
            ->all();

        return Inertia::render('Photos', [
            'og' => OgMeta::gallery(),
            'photos' => $photos,
        ]);
    }

    /**
     * Shape every photo on a single owning model, sharing one card lookup and
     * carrying a `sort` key (dropped before the response) for the cross-model
     * newest-first ordering above.
     *
     * @param  Collection<int, Attachment>  $group  Attachments for one model.
     * @return array<int, array<string, mixed>>
     */
    private function photosForModel(Collection $group): array
    {
        $model = $group->first()->model;
        $sort = $model->occurred_at;

        return array_map(
            fn (array $photo): array => [...$photo, 'sort' => $sort],
            GalleryPhotos::shape($model, $group),
        );
    }
}
