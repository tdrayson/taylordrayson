<?php

namespace App\Models;

use App\Contracts\DefinesContentSchema;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasFlatFile;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Database\Factories\MediaFactory;
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
    'title',
    'rating',
    'source',
    'source_id',
    'meta',
    'timezone',
])]
class Media extends Model implements DefinesContentSchema, HasMedia, Timelineable
{
    /** @use HasFactory<MediaFactory> */
    use HasAttachments, HasFactory, HasFlatFile, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public static function schema(Blueprint $table): void
    {
        $table->id();
        $table->ulid('ulid')->nullable()->unique();
        $table->timestamp('occurred_at')->index();
        $table->string('timezone')->nullable();
        $table->string('type');
        $table->string('title');
        $table->integer('rating')->nullable();
        $table->string('source')->nullable();
        $table->string('source_id')->nullable();
        $table->unsignedBigInteger('series_id')->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
    }

    public function getPlatformUrlAttribute(): ?string
    {
        if ($this->source === 'trakt' && $this->source_id) {
            return "https://trakt.tv/{$this->source_id}";
        }

        return null;
    }

    public function slug(): string
    {
        return Str::slug($this->title);
    }

    public function flatFileType(): string
    {
        return 'media';
    }

    /**
     * @return list<string>
     */
    public function flatFilePathAttributes(): array
    {
        return ['occurred_at', 'title'];
    }

    public function flatFileBaseSlug(bool $original = false): string
    {
        $title = $original
            ? ($this->getOriginal('title') ?? $this->title)
            : $this->title;

        return Str::slug((string) $title);
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
            'kind' => $attributes['type'] ?? null,
            'title' => $attributes['title'] ?? null,
            'rating' => $attributes['rating'] ?? null,
            'source' => $attributes['source'] ?? null,
            'source_id' => $attributes['source_id'] ?? null,
            'meta' => $this->meta,
        ];
    }

    public function card(): array
    {
        $detail = match ($this->type) {
            'film' => $this->meta['year'] ?? null,
            'tv' => isset($this->meta['season'], $this->meta['episode'])
                ? sprintf('S%02dE%02d', $this->meta['season'], $this->meta['episode'])
                : null,
            'book' => $this->meta['author'] ?? null,
            default => null,
        };

        $parts = array_filter([
            $this->rating ? "★ {$this->rating} / 10" : null,
            $detail,
        ]);

        return [
            'type' => 'media',
            'icon' => 'film',
            'title' => $this->title,
            'subtitle' => $parts ? implode(', ', $parts) : null,
            'occurred_at' => $this->occurred_at,
            'accent' => 'media',
            'meta' => [],
        ];
    }
}
