<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'occurred_at',
    'title',
    'slug',
    'excerpt',
    'content',
    'published',
    'timezone',
])]
class Article extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasTags, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'content' => 'array',
            'published' => 'boolean',
        ];
    }

    public function slug(): string
    {
        return $this->getAttribute('slug');
    }

    /**
     * Articles keep their bare author-managed slug in URLs.
     */
    public function urlSlug(): string
    {
        return $this->slug();
    }

    /**
     * Read from the raw attribute so unsaved models resolve to false rather
     * than throwing under strict attribute access.
     */
    public function shouldAppearOnTimeline(): bool
    {
        return (bool) ($this->attributes['published'] ?? false);
    }

    public function card(): array
    {
        return [
            'type' => 'article',
            'icon' => 'file-text',
            'title' => $this->title,
            'subtitle' => $this->excerpt,
            'occurred_at' => $this->occurred_at,
            'accent' => 'article',
            'meta' => [],
        ];
    }
}
