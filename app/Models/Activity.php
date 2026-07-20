<?php

namespace App\Models;

use App\Enums\Source;
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
    'name',
    'description',
    'duration',
    'calories',
    'distance',
    'average_heart_rate',
    'max_heart_rate',
    'heart_rate',
    'altitude',
    'speed',
    'track',
    'source',
    'source_id',
    'timezone',
    'meta',
])]
class Activity extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'heart_rate' => 'array',
            'altitude' => 'array',
            'speed' => 'array',
            'track' => 'array',
            'meta' => 'array',
            'distance' => 'integer',
        ];
    }

    /**
     * Preserve whole-number floats (e.g. 10.0) in the stream columns; without
     * this flag json_encode() drops the trailing zero and round-trips them
     * back as integers, silently changing the stored value's type.
     *
     * @param  string  $key
     */
    protected function getJsonCastFlags($key): int
    {
        return in_array($key, ['heart_rate', 'altitude', 'speed', 'track'], true)
            ? JSON_PRESERVE_ZERO_FRACTION
            : parent::getJsonCastFlags($key);
    }

    public function getPlatformUrlAttribute(): ?string
    {
        if ($this->source === Source::Strava->value && $this->source_id) {
            return "https://www.strava.com/activities/{$this->source_id}";
        }

        return null;
    }

    public function slug(): string
    {
        return Str::slug($this->name ?? $this->type);
    }
}
