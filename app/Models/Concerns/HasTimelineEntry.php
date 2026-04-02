<?php

namespace App\Models\Concerns;

use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasTimelineEntry
{
    public function timelineEntry(): MorphOne
    {
        return $this->morphOne(TimelineEntry::class, 'timelineable');
    }

    public function permalink(): string
    {
        return '/'.$this->occurred_at->format('Y/m/d').'/'.$this->slug();
    }
}
