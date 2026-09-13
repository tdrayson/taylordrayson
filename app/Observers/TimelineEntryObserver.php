<?php

namespace App\Observers;

use App\Models\Concerns\Timelineable;
use App\Models\Food;
use App\Support\EntryInstant;
use App\Support\TimelineUrlSlug;
use Illuminate\Database\Eloquent\Model;

class TimelineEntryObserver
{
    public function saved(Model $model): void
    {
        if ($model instanceof Food) {
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
            ['dataset' => $model->getMorphClass(), 'entry_id' => $model->getKey()],
            [
                'occurred_at' => $model->occurred_at,
                // Recomputed on every save, so it cannot drift from the pair it
                // is derived from.
                'occurred_utc' => EntryInstant::utc($model->occurred_at, $this->timezoneOf($model)),
                'ends_at' => $model->getAttribute('ends_at'),
            ],
        );

        TimelineUrlSlug::ensure($entry, $model->slug());
    }

    /**
     * The entry's own zone. Read through the accessor rather than as an
     * attribute: Timelineable models expose `timezone()` as a method, which
     * getAttribute() would try to resolve as a relationship.
     */
    private function timezoneOf(Model $model): ?string
    {
        return method_exists($model, 'timezone') ? $model->timezone() : null;
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof Food) {
            return;
        }

        $model->timelineEntry()?->delete();
    }
}
