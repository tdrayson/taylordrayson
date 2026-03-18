<?php

namespace App\Models\Concerns;

use App\Models\TimelineEntry;
use App\Observers\TimelineEntryObserver;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasTimelineEntry
{
    public function timelineEntry(): MorphOne
    {
        return $this->morphOne(TimelineEntry::class, 'timelineable');
    }

    public static function bootHasTimelineEntry(): void
    {
        static::observe(TimelineEntryObserver::class);
    }
}
