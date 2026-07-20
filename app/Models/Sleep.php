<?php

namespace App\Models;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\SegmentData;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Carbon\CarbonInterface;
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

    /**
     * Sleep rows are stored at day granularity (midnight); the wake time is
     * the meaningful clock time for the timeline card and entry page.
     */
    public function occurredAtForDisplay(): CarbonInterface
    {
        return $this->wake_time ?? $this->occurred_at;
    }

    public function card(): CardData
    {
        $totalMinutes = intdiv($this->duration, 60);
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        $formatted = $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";

        return new CardData(
            type: 'sleep',
            icon: 'bed',
            title: "{$formatted} sleep",
            titleLabel: "Sleep log, {$formatted}",
            subtitle: $this->bedtime->format('g:ia').' → '.$this->wake_time->format('g:ia'),
            subtitleTokens: null,
            occurredAt: $this->occurred_at,
            accent: 'sleep',
            range: null,
            meta: CardMeta::sleep($this->stageSegments()),
        );
    }

    /**
     * Per-stage durations (seconds) for the timeline breakdown bar.
     *
     * @return list<SegmentData>
     */
    private function stageSegments(): array
    {
        return collect([
            ['label' => 'Awake', 'stage' => 'awake', 'seconds' => (int) $this->awake],
            ['label' => 'REM', 'stage' => 'rem', 'seconds' => (int) $this->rem],
            ['label' => 'Light', 'stage' => 'light', 'seconds' => (int) $this->core],
            ['label' => 'Deep', 'stage' => 'deep', 'seconds' => (int) $this->deep],
        ])
            ->filter(fn (array $segment): bool => $segment['seconds'] > 0)
            ->map(fn (array $segment): SegmentData => new SegmentData($segment['label'], $segment['stage'], $segment['seconds']))
            ->values()
            ->all();
    }
}
