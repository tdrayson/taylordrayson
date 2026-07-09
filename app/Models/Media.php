<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'occurred_at',
    'type',
    'title',
    'rating',
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
            'meta' => 'array',
        ];
    }

    public function getPlatformUrlAttribute(): ?string
    {
        if ($this->source === 'trakt' && $this->source_id) {
            return "https://trakt.tv/{$this->source_id}";
        }

        return null;
    }

    public function slug(): string
    {
        return Str::slug($this->title);
    }

    public function card(): array
    {
        $detail = match ($this->type) {
            'film' => $this->meta['year'] ?? null,
            'tv' => isset($this->meta['season'], $this->meta['episode'])
                ? sprintf('S%02dE%02d', $this->meta['season'], $this->meta['episode'])
                : null,
            'book' => $this->meta['author'] ?? null,
            default => null,
        };

        $parts = array_filter([
            $this->rating ? "★ {$this->rating} / 10" : null,
            $detail,
        ]);

        return [
            'type' => 'media',
            'icon' => 'film',
            'title' => $this->title,
            'subtitle' => $parts ? implode(', ', $parts) : null,
            'occurred_at' => $this->occurred_at,
            'accent' => 'media',
            'meta' => [],
        ];
    }
}
