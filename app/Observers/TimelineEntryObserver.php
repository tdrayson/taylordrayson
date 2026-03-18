<?php

namespace App\Observers;

use App\Models\Calorie;
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

        $model->timelineEntry()->updateOrCreate(
            ['timelineable_type' => $model->getMorphClass(), 'timelineable_id' => $model->getKey()],
            ['occurred_at' => $model->occurred_at],
        );
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof Calorie) {
            return;
        }

        $model->timelineEntry()?->delete();
    }
}
