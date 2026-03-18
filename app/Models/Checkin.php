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
    'venue_name',
    'category',
    'address',
    'city',
    'county',
    'country',
    'latitude',
    'longitude',
    'description',
    'platform_type',
    'platform_id',
])]
class Checkin extends Model implements Timelineable
{
    use HasAssets, HasFactory, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function getPlatformUrlAttribute(): ?string
    {
        if ($this->platform_type === 'swarm' && $this->platform_id) {
            return "https://www.swarmapp.com/checkin/{$this->platform_id}";
        }

        return null;
    }

    /**
     * @return array{type: string, icon: string, title: string, subtitle: ?string, occurred_at: Carbon, accent: string, meta: array}
     */
    public function toTimelineCard(): array
    {
        return [
            'type' => 'checkin',
            'icon' => 'map-pin',
            'title' => $this->venue_name,
            'subtitle' => $this->category,
            'occurred_at' => $this->occurred_at,
            'accent' => 'checkin',
            'meta' => [],
        ];
    }
}
