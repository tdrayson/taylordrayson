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

    public function url(): string
    {
        return '/'.$this->occurred_at->format('Y/m/d').'/'.$this->urlSlug();
    }

    public function urlSlug(): string
    {
        return $this->slug().'-'.$this->getKey();
    }

    /**
     * The timezone the entry was captured in; null renders as home time.
     * Read from the raw attributes so unsaved models resolve to null.
     */
    public function timezone(): ?string
    {
        return $this->attributes['timezone'] ?? null;
    }

    /**
     * Every Timelineable appears on the spine by default; models with their
     * own publication gate (e.g. Article) override this.
     */
    public function shouldAppearOnTimeline(): bool
    {
        return true;
    }
}
