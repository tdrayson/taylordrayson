<?php

namespace App\Models;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\PhotoData;
use App\Data\SubtitleToken;
use App\Enums\Source;
use App\Enums\TimelineType;
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

    public function card(): CardData
    {
        return new CardData(
            type: TimelineType::Activity,
            icon: 'footprints',
            title: $this->name ?? ucfirst($this->type),
            titleLabel: null,
            subtitle: $this->cardSubtitle(),
            subtitleTokens: $this->subtitleTokens(),
            occurredAt: $this->occurred_at,
            accent: 'activity',
            range: null,
            meta: CardMeta::activity(
                polyline: data_get($this->meta, 'polyline'),
                photos: $this->photoData(),
                map: $this->getFirstMediaUrl('map') ?: null,
                mapDark: $this->getFirstMediaUrl('map_dark') ?: null,
            ),
        );
    }

    /**
     * @return list<PhotoData>
     */
    private function photoData(): array
    {
        return array_map(
            fn (array $photo): PhotoData => PhotoData::gallery($photo['src'], $photo['srcset'], $photo['full'], $photo['latitude'], $photo['longitude']),
            $this->galleryPhotos(),
        );
    }

    private function cardSubtitle(): ?string
    {
        // Data-driven, not a hardcoded cardio type list: strength activities
        // carry sets; everything else describes itself by whatever metrics it
        // recorded, so new distance-based types scale in without an allow-list.
        if (is_array($this->meta['sets'] ?? null)) {
            return $this->strengthSubtitle($this->meta['sets']);
        }

        $parts = [];

        if ($this->distance) {
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
     * @return list<SubtitleToken>|null
     */
    private function subtitleTokens(): ?array
    {
        // Data-driven (see cardSubtitle): sets => strength; otherwise show
        // whatever metrics exist, so new distance types need no allow-list.
        if (is_array($this->meta['sets'] ?? null)) {
            return $this->strengthTokens($this->meta['sets']);
        }

        $tokens = [];

        if ($this->distance) {
            $tokens[] = SubtitleToken::dist((int) $this->distance, 1);
        }

        if ($this->duration) {
            $tokens[] = SubtitleToken::text($this->durationForHumans($this->duration));
        }

        if ($this->calories) {
            $tokens[] = SubtitleToken::text(number_format($this->calories).' kcal');
        }

        return $tokens ?: null;
    }

    /**
     * @param  array<int, array{exercise: string, reps: int, weight: float}>  $sets
     * @return list<SubtitleToken>
     */
    private function strengthTokens(array $sets): array
    {
        $exercises = count(array_unique(array_column($sets, 'exercise')));
        $volume = array_sum(array_map(fn (array $set): float => ($set['reps'] ?? 0) * ($set['weight_kg'] ?? $set['weight'] ?? 0), $sets));

        $tokens = [
            SubtitleToken::text($exercises.' '.Str::plural('exercise', $exercises)),
            SubtitleToken::text(count($sets).' '.Str::plural('set', count($sets))),
        ];

        if ($volume > 0) {
            $tokens[] = SubtitleToken::wt($volume, 0);
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
