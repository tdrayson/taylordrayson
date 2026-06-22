<?php

namespace App\Models;

use App\Models\Concerns\HasAssets;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'occurred_at',
    'type',
    'name',
    'duration',
    'calories',
    'distance_km',
    'average_heart_rate',
    'max_heart_rate',
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

    public function slug(): string
    {
        return Str::slug($this->name ?? $this->type);
    }

    public function card(): array
    {
        return [
            'type' => 'activity',
            'icon' => 'footprints',
            'title' => $this->name ?? ucfirst($this->type),
            'subtitle' => $this->cardSubtitle(),
            'occurred_at' => $this->occurred_at,
            'accent' => 'activity',
            'meta' => [],
        ];
    }

    private function cardSubtitle(): ?string
    {
        $isCardio = in_array($this->type, ['run', 'cycle', 'ride', 'swim', 'walk', 'hike']);

        if (! $isCardio && is_array($this->meta['sets'] ?? null)) {
            return $this->strengthSubtitle($this->meta['sets']);
        }

        $parts = [];

        if ($isCardio && $this->distance_km) {
            $parts[] = round($this->distance_km, 2).' km';
        }

        if ($this->duration) {
            $parts[] = $this->durationForHumans($this->duration);
        }

        if ($this->calories) {
            $parts[] = number_format($this->calories).' kcal';
        }

        return $parts ? implode(' · ', $parts) : null;
    }

    /**
     * @param  array<int, array{exercise: string, reps: int, weight: float}>  $sets
     */
    private function strengthSubtitle(array $sets): string
    {
        $exercises = count(array_unique(array_column($sets, 'exercise')));
        $volume = array_sum(array_map(fn (array $set): float => ($set['reps'] ?? 0) * ($set['weight_kg'] ?? $set['weight'] ?? 0), $sets));

        $parts = [
            $exercises.' '.Str::plural('exercise', $exercises),
            count($sets).' '.Str::plural('set', count($sets)),
        ];

        if ($volume > 0) {
            $parts[] = number_format($volume).' kg';
        }

        return implode(' · ', $parts);
    }

    private function durationForHumans(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $hours = intdiv($minutes, 60);

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes % 60);
        }

        return "{$minutes}m";
    }
}
