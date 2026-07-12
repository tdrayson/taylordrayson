<?php

namespace App\Models;

use App\Contracts\DefinesContentSchema;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasFlatFile;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use App\Support\Distance;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'ulid',
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
class Activity extends Model implements DefinesContentSchema, HasMedia, Timelineable
{
    /** @use HasFactory<ActivityFactory> */
    use HasAttachments, HasFactory, HasFlatFile, HasTimelineEntry;

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

    public static function schema(Blueprint $table): void
    {
        $table->id();
        $table->ulid('ulid')->nullable()->unique();
        $table->timestamp('occurred_at')->index();
        $table->string('timezone')->nullable();
        $table->string('type');
        $table->string('name')->nullable();
        $table->text('description')->nullable();
        $table->integer('duration')->nullable();
        $table->integer('calories')->nullable();
        $table->integer('distance')->nullable();
        $table->integer('average_heart_rate')->nullable();
        $table->integer('max_heart_rate')->nullable();
        $table->json('heart_rate')->nullable();
        $table->string('source')->nullable();
        $table->string('source_id')->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
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

    public function flatFileType(): string
    {
        return 'activity';
    }

    /**
     * @return list<string>
     */
    public function flatFilePathAttributes(): array
    {
        return ['occurred_at', 'name', 'type'];
    }

    public function flatFileBaseSlug(bool $original = false): string
    {
        if ($original) {
            $name = $this->getOriginal('name') ?? $this->getOriginal('type');

            return Str::slug((string) ($name ?? $this->type));
        }

        return $this->slug();
    }

    public function flatFileBody(): string
    {
        return (string) ($this->getAttributes()['description'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function flatFileMeta(): array
    {
        $attributes = $this->getAttributes();

        return [
            'kind' => $attributes['type'] ?? null,
            'name' => $attributes['name'] ?? null,
            'duration' => $attributes['duration'] ?? null,
            'calories' => $attributes['calories'] ?? null,
            'distance' => $attributes['distance'] ?? null,
            'average_heart_rate' => $attributes['average_heart_rate'] ?? null,
            'max_heart_rate' => $attributes['max_heart_rate'] ?? null,
            'heart_rate' => $this->heart_rate,
            'source' => $attributes['source'] ?? null,
            'source_id' => $attributes['source_id'] ?? null,
            'meta' => $this->meta,
        ];
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
     * @return list<array{t: 'dist', m: int, p: int}|array{t: 'wt', kg: float, p: int}|array{t: 'text', v: string}>|null
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
     * @return list<array{t: 'text', v: string}|array{t: 'wt', kg: float, p: int}>
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
