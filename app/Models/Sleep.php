<?php

namespace App\Models;

use App\Contracts\DefinesContentSchema;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasFlatFile;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Carbon\CarbonInterface;
use Database\Factories\SleepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'ulid',
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
    'timezone',
])]
class Sleep extends Model implements DefinesContentSchema, HasMedia, Timelineable
{
    /** @use HasFactory<SleepFactory> */
    use HasAttachments, HasFactory, HasFlatFile, HasTimelineEntry;

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

    public static function schema(Blueprint $table): void
    {
        $table->id();
        $table->ulid('ulid')->nullable()->unique();
        $table->timestamp('occurred_at')->index();
        $table->string('timezone')->nullable();
        $table->timestamp('bedtime')->nullable();
        $table->timestamp('wake_time')->nullable();
        $table->integer('duration')->nullable();
        $table->integer('awake')->nullable();
        $table->integer('rem')->nullable();
        $table->integer('core')->nullable();
        $table->integer('deep')->nullable();
        $table->string('source')->nullable();
        $table->json('stages')->nullable();
        $table->integer('score')->nullable();
        $table->integer('duration_score')->nullable();
        $table->integer('bedtime_score')->nullable();
        $table->integer('interruption_score')->nullable();
        $table->timestamps();
    }

    public function slug(): string
    {
        return 'sleep';
    }

    public function flatFileType(): string
    {
        return 'sleep';
    }

    /**
     * @return list<string>
     */
    public function flatFilePathAttributes(): array
    {
        return ['occurred_at'];
    }

    public function flatFileBaseSlug(bool $original = false): string
    {
        return 'sleep';
    }

    public function flatFileBody(): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function flatFileMeta(): array
    {
        $attributes = $this->getAttributes();

        return [
            'bedtime' => $this->bedtime?->toIso8601String(),
            'wake_time' => $this->wake_time?->toIso8601String(),
            'duration' => $attributes['duration'] ?? null,
            'awake' => $attributes['awake'] ?? null,
            'rem' => $attributes['rem'] ?? null,
            'core' => $attributes['core'] ?? null,
            'deep' => $attributes['deep'] ?? null,
            'source' => $attributes['source'] ?? null,
            'stages' => $this->stages,
            'score' => $attributes['score'] ?? null,
            'duration_score' => $attributes['duration_score'] ?? null,
            'bedtime_score' => $attributes['bedtime_score'] ?? null,
            'interruption_score' => $attributes['interruption_score'] ?? null,
        ];
    }

    /**
     * Sleep rows are stored at day granularity (midnight); the wake time is
     * the meaningful clock time for the timeline card and entry page.
     */
    public function occurredAtForDisplay(): CarbonInterface
    {
        return $this->wake_time ?? $this->occurred_at;
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
            'titleLabel' => "Sleep log, {$formatted}",
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
