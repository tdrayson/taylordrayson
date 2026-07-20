<?php

namespace App\Models;

use App\Enums\MediaType;
use App\Enums\Source;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
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
    use HasAttachments, HasFactory, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'type' => MediaType::class,
            'meta' => 'array',
        ];
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function getPlatformUrlAttribute(): ?string
    {
        if ($this->source !== Source::Trakt->value) {
            return null;
        }

        $slug = $this->meta['ids']['slug'] ?? null;

        if ($this->type === MediaType::Film && $slug) {
            return "https://trakt.tv/movies/{$slug}";
        }

        $showSlug = $this->meta['show_slug'] ?? null;
        if ($this->type === MediaType::TvEpisode && $showSlug && isset($this->meta['season'], $this->meta['episode'])) {
            return "https://trakt.tv/shows/{$showSlug}/seasons/{$this->meta['season']}/episodes/{$this->meta['episode']}";
        }

        return null;
    }

    public function slug(): string
    {
        return Str::slug($this->title);
    }
}
