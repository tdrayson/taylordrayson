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
 * Every real photo across the timeline, newest first, backing both the /photos
 * gallery and the "Life lately" widget so the two cannot drift. Enrichment art
 * (video thumbnails, film and book posters) is excluded.
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

        // Ordered up front so only the photos actually wanted pay for card
        // presentation and URL generation.
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
            // Non-timeline models (Series) use cover/photos for their own art, so
            // this guards on Timelineable rather than on null.
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
