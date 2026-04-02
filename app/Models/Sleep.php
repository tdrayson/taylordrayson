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
    'bedtime',
    'wake_time',
    'duration',
    'awake',
    'rem',
    'core',
    'deep',
    'source',
    'stages',
])]
class Sleep extends Model implements Timelineable
{
    use HasAssets, HasFactory, HasTimelineEntry;

    protected $table = 'sleep';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'bedtime' => 'datetime',
            'wake_time' => 'datetime',
            'stages' => 'array',
        ];
    }

    /**
     * @return array{type: string, icon: string, title: string, subtitle: ?string, occurred_at: Carbon, accent: string, meta: array}
     */
    public function toTimelineCard(): array
    {
        $totalMinutes = intdiv($this->duration, 60);
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        $formatted = $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";

        return [
            'type' => 'sleep',
            'icon' => 'bed',
            'title' => "{$formatted} sleep",
            'subtitle' => null,
            'occurred_at' => $this->occurred_at,
            'accent' => 'sleep',
            'meta' => [],
        ];
    }
}
