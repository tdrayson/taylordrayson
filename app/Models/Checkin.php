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
    'venue_name',
    'category',
    'address',
    'city',
    'county',
    'country',
    'latitude',
    'longitude',
    'description',
    'is_mayor',
    'source',
    'source_id',
])]
class Checkin extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'is_mayor' => 'boolean',
        ];
    }

    public function getPlatformUrlAttribute(): ?string
    {
        if ($this->source === 'swarm' && $this->source_id) {
            return "https://www.swarmapp.com/checkin/{$this->source_id}";
        }

        return null;
    }

    public function slug(): string
    {
        return Str::slug($this->venue_name);
    }

    public function card(): array
    {
        $parts = array_filter([$this->category, $this->city]);

        return [
            'type' => 'checkin',
            'icon' => 'map-pin',
            'title' => $this->venue_name,
            'subtitle' => $parts ? implode(', ', $parts) : null,
            'occurred_at' => $this->occurred_at,
            'accent' => 'checkin',
            'meta' => [
                'map' => $this->getFirstMediaUrl('map') ?: null,
                'mapDark' => $this->getFirstMediaUrl('map_dark') ?: null,
            ],
        ];
    }
}
