<?php

namespace App\Models;

use App\Data\TvEpisodeMeta;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasStatus;
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
#[Fillable(['occurred_at', 'title', 'rating', 'tv_show_id', 'timezone', 'source', 'source_id', 'meta', 'status', 'password'])]
final class TvEpisode extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasStatus, HasSubjects, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'meta' => TvEpisodeMeta::class,
        ];
    }

    public function tvShow(): BelongsTo
    {
        return $this->belongsTo(TvShow::class);
    }

    /**
     * Kept because Activity and Place expose the same attribute and
     * EntryController reads it off whichever model it was handed. The URL
     * shapes themselves live in TraktUrl, so the model no longer knows how a
     * Trakt link is spelled.
     */
    public function getPlatformUrlAttribute(): ?string
    {
        return TraktUrl::forEpisode($this);
    }

    public function slug(): string
    {
        return Str::slug($this->title);
    }
}
