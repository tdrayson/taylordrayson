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

    /**
     * Latest a nap can start, and shortest a night can be.
     *
     * Both are needed. Bedtime alone catches an early night, since an 18:00
     * start is as often a long one as a doze; duration alone catches a short
     * night, most of which begin between 2am and 6am.
     */
    private const NAP_LATEST_START = 20;

    private const NAP_LONGEST = 6 * 60 * 60;

    /**
     * Whether this is a nap rather than a night: begun and ended inside the
     * same waking day.
     *
     * Computed rather than stored, so it stays one rule in one place. Seventeen
     * of 1,772 rows qualify.
     */
    public function isNap(): bool
    {
        if ($this->bedtime === null || $this->wake_time === null) {
            return false;
        }

        return $this->bedtime->hour >= 8
            && $this->bedtime->hour < self::NAP_LATEST_START
            && $this->duration < self::NAP_LONGEST
            && $this->bedtime->isSameDay($this->wake_time);
    }

    /** A nap is not the night's sleep, so the timeline leaves it out. */
    public function shouldAppearOnTimeline(): bool
    {
        return ! $this->isNap();
    }
}
