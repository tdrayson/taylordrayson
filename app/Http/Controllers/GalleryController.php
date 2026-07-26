<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Attachment;
use App\Models\Concerns\Timelineable;
use App\Models\Media;
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
     * every entry, newest first. Enrichment art is deliberately excluded so the
     * gallery only shows photos actually taken: appearance video thumbnails and
     * media (film/TV/book) posters by model type, plus any non-timeline model
     * (e.g. a Series poster) via the Timelineable guard below.
     */
    public function index(): Response
    {
        $attachments = Attachment::query()
            ->whereIn('collection_name', ['cover', 'photos'])
            ->whereNotIn('model_type', [Appearance::class, Media::class])
            ->with(['model' => fn (MorphTo $morphTo) => $morphTo->morphWith([Activity::class => ['media']])])
            ->get();

        $photos = $attachments
            // Only timeline entries belong in the photo gallery. Some non-timeline
            // models (e.g. Series) also use the cover/photos collections for their
            // own art, so guard on Timelineable rather than a mere null check.
            ->filter(fn (Attachment $attachment): bool => $attachment->model instanceof Timelineable)
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
