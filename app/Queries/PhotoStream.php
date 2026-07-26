<?php

namespace App\Queries;

use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Attachment;
use App\Models\Concerns\Timelineable;
use App\Models\Media;
use App\Support\GalleryPhotos;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * The stream of real photos (the cover + photos collections) across every
 * timeline entry, newest first by the entry's date. This is the single source
 * for both the /photos gallery and the "Life lately" widget on /now, so the two
 * can never drift apart on what counts as a photo or how they are ordered.
 *
 * Enrichment art is deliberately excluded so only photos actually taken remain:
 * appearance video thumbnails and media (film/TV/book) posters by model type,
 * plus any non-timeline model (e.g. a Series poster) via the Timelineable guard.
 */
final class PhotoStream
{
    /**
     * @param  int|null  $limit  Stop once this many photos are shaped; null shapes every photo.
     * @return list<array<string, mixed>>
     */
    public function __invoke(?int $limit = null): array
    {
        $photos = [];

        // Ordering is decided cheaply up front, so only the photos actually
        // wanted get the expensive card presentation and URL generation. The
        // gallery shapes everything (null); the /now deck only its first few.
        foreach ($this->orderedGroups() as $group) {
            foreach (GalleryPhotos::shape($group['model'], $group['media']) as $photo) {
                $photos[] = $photo;

                if ($limit !== null && count($photos) >= $limit) {
                    return $photos;
                }
            }
        }

        return $photos;
    }

    /**
     * Every timeline entry that owns photos, paired with its media and ordered
     * newest first. Ties on the same date fall back to the model key so the
     * order is stable across requests and environments. Loading the rows is
     * cheap; the cost this defers is the per-photo shaping in __invoke().
     *
     * @return Collection<int, array{model: Model&Timelineable, media: EloquentCollection<int, Attachment>}>
     */
    private function orderedGroups(): Collection
    {
        return Attachment::query()
            ->whereIn('collection_name', ['cover', 'photos'])
            ->whereNotIn('model_type', [Appearance::class, Media::class])
            ->with(['model' => fn (MorphTo $morphTo) => $morphTo->morphWith([Activity::class => ['media']])])
            ->get()
            // Some non-timeline models (e.g. Series) also use the cover/photos
            // collections for their own art, so guard on Timelineable rather
            // than a mere null check.
            ->filter(fn (Attachment $attachment): bool => $attachment->model instanceof Timelineable)
            ->groupBy(fn (Attachment $attachment): string => $attachment->model_type.':'.$attachment->model_id)
            ->map(fn (EloquentCollection $group): array => [
                'model' => $group->first()->model,
                'media' => $group,
            ])
            ->sortByDesc(fn (array $group): string => sprintf(
                '%011d:%011d',
                $group['model']->occurred_at->timestamp,
                $group['model']->getKey(),
            ))
            ->values();
    }
}
