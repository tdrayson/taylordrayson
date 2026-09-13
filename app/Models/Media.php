<?php

namespace App\Models;

use App\Data\MediaMeta;
use App\Enums\MediaType;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasSubjects;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use App\Support\TraktUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'occurred_at',
    'type',
    'title',
    'rating',
    'series_id',
    'timezone',
    'source',
    'source_id',
    'meta',
])]
class Media extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasSubjects, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'type' => MediaType::class,
            'meta' => MediaMeta::class,
        ];
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /**
     * Kept because Activity and Checkin expose the same attribute and
     * EntryController reads it off whichever model it was handed. The URL
     * shapes themselves live in TraktUrl, so the model no longer knows how a
     * Trakt link is spelled.
     */
    public function getPlatformUrlAttribute(): ?string
    {
        return TraktUrl::forMedia($this);
    }

    public function slug(): string
    {
        return Str::slug($this->title);
    }
}
