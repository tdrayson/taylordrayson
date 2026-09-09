<?php

namespace App\Queries;

use App\Models\Attachment;
use App\Models\Concerns\Timelineable;
use App\Support\GalleryPhotos;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * Every real photo across the timeline, newest first, backing both the /photos
 * gallery and the "Life lately" widget so the two cannot drift. Enrichment art
 * (video thumbnails, film and book posters) is excluded by
 * GalleryPhotos::contributesPhotos().
 */
final class PhotoStream
{
    /**
     * @var Collection<int, array{model: Model&Timelineable, media: EloquentCollection<int, Attachment>}>|null
     */
    private ?Collection $groups = null;

    /**
     * @param  int|null  $limit  Stop once this many photos are shaped; null shapes every photo.
     * @param  int  $offset  Photos to skip before shaping starts.
     * @return list<array<string, mixed>>
     */
    public function __invoke(?int $limit = null, int $offset = 0): array
    {
        if ($limit !== null && $limit < 1) {
            return [];
        }

        $offset = max(0, $offset);
        $photos = [];
        $seen = 0;

        // Ordered up front so only the photos actually wanted pay for caption
        // presentation and URL generation. Skipped groups are counted, not
        // shaped, so paging deeper costs a count rather than a page of work.
        foreach ($this->orderedGroups() as $group) {
            $size = $group['media']->count();

            if ($seen + $size <= $offset) {
                $seen += $size;

                continue;
            }

            foreach (GalleryPhotos::shape($group['model'], $group['media']) as $index => $photo) {
                if ($seen + $index < $offset) {
                    continue;
                }

                $photos[] = $photo;

                if ($limit !== null && count($photos) >= $limit) {
                    return $photos;
                }
            }

            $seen += $size;
        }

        return $photos;
    }

    /**
     * A page of the stream, ready for Inertia::scroll(). Only the requested
     * page is shaped; the total is counted in the database rather than by
     * hydrating every photo to measure it.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        $total = $this->count();

        // Clamped, so ?page=999 returns the last page rather than an empty grid
        // under a heading that promises hundreds of photos.
        $page = max(1, min($page, (int) max(1, ceil($total / max(1, $perPage)))));

        return new LengthAwarePaginator(
            $this($perPage, ($page - 1) * $perPage),
            $total,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * How many photos the gallery holds, counted in the database rather than by
     * hydrating the stream.
     *
     * Non-timeline owners (a Series poster, a Page cover) are excluded by the
     * same rule contributesPhotos() applies, expressed here as model types.
     */
    public function count(): int
    {
        return Attachment::query()
            ->whereIn('collection_name', ['cover', 'photos'])
            ->whereIn('model_type', GalleryPhotos::includedModels())
            ->count();
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
        if ($this->groups !== null) {
            return $this->groups;
        }

        $attachments = Attachment::query()
            ->whereIn('collection_name', ['cover', 'photos'])
            ->whereIn('model_type', GalleryPhotos::includedModels())
            ->get();

        return $this->groups = $attachments
            ->load(['model' => fn (MorphTo $morphTo) => $morphTo->morphWith($this->captionRelations($attachments))])
            ->filter(fn (Attachment $attachment): bool => GalleryPhotos::contributesPhotos($attachment->model))
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

    /**
     * Eager-load `media` and `timelineEntry` on every morph type actually
     * present, rather than a hardcoded list a new photo-owning type could fall
     * off. A caption reads the permalink off the timeline entry, and the few
     * types still routed through a card presenter read their own attachments;
     * left lazy that is two queries per entry.
     *
     * Types the gallery then filters out (a Series poster, a Page cover) reach
     * here too, and only Timelineable models have a timeline entry to load.
     *
     * @param  EloquentCollection<int, Attachment>  $attachments
     * @return array<class-string, list<string>>
     */
    private function captionRelations(EloquentCollection $attachments): array
    {
        return $attachments
            ->pluck('model_type')
            ->unique()
            ->filter(fn (string $type): bool => class_exists($type))
            ->mapWithKeys(fn (string $type): array => [
                $type => is_a($type, Timelineable::class, true)
                    ? ['media', 'timelineEntry']
                    : ['media'],
            ])
            ->all();
    }
}
