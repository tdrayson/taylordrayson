<?php

namespace App\Models;

use App\Models\Concerns\HasAssets;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'occurred_at',
    'type',
    'title',
    'rating',
    'platform_type',
    'platform_id',
    'meta',
])]
class Media extends Model implements Timelineable
{
    use HasAssets, HasFactory, HasTimelineEntry;

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
        if ($this->platform_type === 'trakt' && $this->platform_id) {
            return "https://trakt.tv/{$this->platform_id}";
        }

        return null;
    }

    /**
     * @return array{type: string, icon: string, title: string, subtitle: ?string, occurred_at: Carbon, accent: string, meta: array}
     */
    public function toTimelineCard(): array
    {
        $subtitle = match ($this->type) {
            'film' => $this->meta['year'] ?? null,
            'tv' => isset($this->meta['season'], $this->meta['episode'])
                ? sprintf('S%02dE%02d', $this->meta['season'], $this->meta['episode'])
                : null,
            'book' => $this->meta['author'] ?? null,
            default => null,
        };

        return [
            'type' => 'media',
            'icon' => 'film',
            'title' => $this->title,
            'subtitle' => $subtitle,
            'occurred_at' => $this->occurred_at,
            'accent' => 'media',
            'meta' => [],
        ];
    }
}
