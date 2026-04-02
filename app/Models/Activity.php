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
    'name',
    'duration',
    'calories',
    'distance_km',
    'heart_rate',
    'platform_type',
    'platform_id',
    'meta',
])]
class Activity extends Model implements Timelineable
{
    use HasAssets, HasFactory, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'heart_rate' => 'array',
            'meta' => 'array',
        ];
    }

    public function getPlatformUrlAttribute(): ?string
    {
        if ($this->platform_type === 'strava' && $this->platform_id) {
            return "https://www.strava.com/activities/{$this->platform_id}";
        }

        return null;
    }

    /**
     * @return array{type: string, icon: string, title: string, subtitle: ?string, occurred_at: Carbon, accent: string, meta: array}
     */
    public function toTimelineCard(): array
    {
        $isCardio = in_array($this->type, ['run', 'cycle', 'swim', 'walk', 'hike']);

        if ($isCardio && $this->distance_km) {
            $distance = round($this->distance_km, 2).' km';
            $duration = gmdate('H:i:s', $this->duration);
            $subtitle = "{$distance} · {$duration}";
        } elseif ($this->duration) {
            $duration = gmdate('H:i:s', $this->duration);
            $subtitle = $duration;
        } else {
            $subtitle = null;
        }

        return [
            'type' => 'activity',
            'icon' => 'footprints',
            'title' => $this->name ?? ucfirst($this->type),
            'subtitle' => $subtitle,
            'occurred_at' => $this->occurred_at,
            'accent' => 'activity',
            'meta' => [],
        ];
    }
}
