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
use Spatie\MediaLibrary\HasMedia;

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
    'score',
    'duration_score',
    'bedtime_score',
    'interruption_score',
])]
class Sleep extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasTimelineEntry;

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

    public function slug(): string
    {
        return 'sleep';
    }

    public function card(): array
    {
        $totalMinutes = intdiv($this->duration, 60);
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        $formatted = $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";

        return [
            'type' => 'sleep',
            'icon' => 'bed',
            'title' => "{$formatted} sleep",
            'subtitle' => $this->bedtime->format('g:ia').' → '.$this->wake_time->format('g:ia'),
            'occurred_at' => $this->occurred_at,
            'accent' => 'sleep',
            'meta' => ['segments' => $this->stageSegments()],
        ];
    }

    /**
     * Per-stage durations (seconds) for the timeline breakdown bar.
     *
     * @return array<int, array{label: string, stage: string, seconds: int}>
     */
    private function stageSegments(): array
    {
        return collect([
            ['label' => 'Awake', 'stage' => 'awake', 'seconds' => (int) $this->awake],
            ['label' => 'REM', 'stage' => 'rem', 'seconds' => (int) $this->rem],
            ['label' => 'Light', 'stage' => 'light', 'seconds' => (int) $this->core],
            ['label' => 'Deep', 'stage' => 'deep', 'seconds' => (int) $this->deep],
        ])->filter(fn (array $segment): bool => $segment['seconds'] > 0)->values()->all();
    }
}
