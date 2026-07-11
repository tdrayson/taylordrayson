<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use App\Support\Distance;
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
            'meta' => 'array',
            'distance' => 'integer',
        ];
    }

    public function getPlatformUrlAttribute(): ?string
    {
        if ($this->source === 'strava' && $this->source_id) {
            return "https://www.strava.com/activities/{$this->source_id}";
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
            'subtitleTokens' => $this->subtitleTokens(),
            'occurred_at' => $this->occurred_at,
            'accent' => 'activity',
            'meta' => [
                'polyline' => data_get($this->meta, 'polyline'),
                'photos' => $this->galleryPhotos(),
            ],
        ];
    }

    private function cardSubtitle(): ?string
    {
        $isCardio = in_array($this->type, ['run', 'cycle', 'ride', 'swim', 'walk', 'hike']);

        if (! $isCardio && is_array($this->meta['sets'] ?? null)) {
            return $this->strengthSubtitle($this->meta['sets']);
        }

        $parts = [];

        if ($isCardio && $this->distance) {
            $parts[] = Distance::miles($this->distance, 1).' mi';
        }

        if ($this->duration) {
            $parts[] = $this->durationForHumans($this->duration);
        }

        if ($this->calories) {
            $parts[] = number_format($this->calories).' kcal';
        }

        return $parts ? implode(', ', $parts) : null;
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

        return implode(', ', $parts);
    }

    /**
     * Structured counterpart to cardSubtitle(): distance/weight are emitted as raw
     * tokens (metres/kg) instead of pre-formatted strings, so FeedItem.vue can
     * compose them through useFormat() and react to the visitor's unit toggle.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function subtitleTokens(): ?array
    {
        $isCardio = in_array($this->type, ['run', 'cycle', 'ride', 'swim', 'walk', 'hike']);

        if (! $isCardio && is_array($this->meta['sets'] ?? null)) {
            return $this->strengthTokens($this->meta['sets']);
        }

        $tokens = [];

        if ($isCardio && $this->distance) {
            $tokens[] = ['t' => 'dist', 'm' => (int) $this->distance, 'p' => 1];
        }

        if ($this->duration) {
            $tokens[] = ['t' => 'text', 'v' => $this->durationForHumans($this->duration)];
        }

        if ($this->calories) {
            $tokens[] = ['t' => 'text', 'v' => number_format($this->calories).' kcal'];
        }

        return $tokens ?: null;
    }

    /**
     * @param  array<int, array{exercise: string, reps: int, weight: float}>  $sets
     * @return array<int, array<string, mixed>>
     */
    private function strengthTokens(array $sets): array
    {
        $exercises = count(array_unique(array_column($sets, 'exercise')));
        $volume = array_sum(array_map(fn (array $set): float => ($set['reps'] ?? 0) * ($set['weight_kg'] ?? $set['weight'] ?? 0), $sets));

        $tokens = [
            ['t' => 'text', 'v' => $exercises.' '.Str::plural('exercise', $exercises)],
            ['t' => 'text', 'v' => count($sets).' '.Str::plural('set', count($sets))],
        ];

        if ($volume > 0) {
            $tokens[] = ['t' => 'wt', 'kg' => $volume, 'p' => 0];
        }

        return $tokens;
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
