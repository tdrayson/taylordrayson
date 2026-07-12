<?php

namespace App\Observers;

use App\Content\UrlSlugAllocator;
use App\Models\Calorie;
use App\Models\Concerns\Timelineable;
use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Model;

class TimelineEntryObserver
{
    public function saved(Model $model): void
    {
        if ($model instanceof Calorie) {
            return;
        }

        if ($model->occurred_at === null) {
            return;
        }

        if ($model instanceof Timelineable && ! $model->shouldAppearOnTimeline()) {
            $model->timelineEntry()->delete();

            return;
        }

        $entry = $model->timelineEntry()->updateOrCreate(
            ['timelineable_type' => $model->getMorphClass(), 'timelineable_id' => $model->getKey()],
            ['occurred_at' => $model->occurred_at, 'ends_at' => $model->getAttribute('ends_at')],
        );

        $this->ensureUrlSlug($model, $entry);
    }

    /**
     * Assign the entry's URL slug once at write time: the first same-day
     * holder keeps the bare slug, collisions get -2/-3... Stored so URLs
     * never reshuffle when earlier entries are backfilled or deleted; only
     * a changed base slug or a date move triggers reassignment.
     */
    private function ensureUrlSlug(Model $model, TimelineEntry $entry): void
    {
        $model->setRelation('timelineEntry', $entry);

        $candidate = app(UrlSlugAllocator::class)->allocate($model);

        if ($entry->url_slug === $candidate) {
            return;
        }

        $entry->url_slug = $candidate;
        $entry->save();
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof Calorie) {
            return;
        }

        $model->timelineEntry()?->delete();
    }
}
