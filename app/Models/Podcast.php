<?php

namespace App\Models;

use App\Contracts\DefinesContentSchema;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasFlatFile;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Database\Factories\PodcastFactory;
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
    'season_number',
    'episode_number',
    'topic',
    'show_notes',
    'transcript',
    'duration',
    'audio_url',
    'video_url',
    'thumbnail',
    'cover_image',
    'timezone',
])]
class Podcast extends Model implements DefinesContentSchema, HasMedia, Timelineable
{
    /** @use HasFactory<PodcastFactory> */
    use HasAttachments;

    use HasFactory;
    use HasFlatFile;
    use HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'duration' => 'integer',
        ];
    }

    public static function schema(Blueprint $table): void
    {
        $table->id();
        $table->ulid('ulid')->nullable()->unique();
        $table->timestamp('occurred_at')->index();
        $table->string('timezone')->nullable();
        $table->integer('season_number');
        $table->integer('episode_number');
        $table->string('topic')->nullable();
        $table->text('show_notes')->nullable();
        $table->text('transcript')->nullable();
        $table->integer('duration')->nullable();
        $table->string('audio_url')->nullable();
        $table->string('video_url')->nullable();
        $table->string('thumbnail')->nullable();
        $table->string('cover_image')->nullable();
        $table->timestamps();
    }

    public function getTitleAttribute(): string
    {
        return "Season {$this->season_number}, Episode {$this->episode_number}";
    }

    public function slug(): string
    {
        return "tww-s{$this->season_number}-e{$this->episode_number}";
    }

    public function flatFileType(): string
    {
        return 'podcast';
    }

    /**
     * @return list<string>
     */
    public function flatFilePathAttributes(): array
    {
        return ['occurred_at', 'season_number', 'episode_number'];
    }

    public function flatFileBaseSlug(bool $original = false): string
    {
        if ($original) {
            $season = $this->getOriginal('season_number') ?? $this->season_number;
            $episode = $this->getOriginal('episode_number') ?? $this->episode_number;

            return "tww-s{$season}-e{$episode}";
        }

        return $this->slug();
    }

    public function flatFileBody(): string
    {
        return (string) ($this->getAttributes()['show_notes'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function flatFileMeta(): array
    {
        $attributes = $this->getAttributes();

        return [
            'season_number' => $attributes['season_number'] ?? null,
            'episode_number' => $attributes['episode_number'] ?? null,
            'topic' => $attributes['topic'] ?? null,
            'transcript' => $attributes['transcript'] ?? null,
            'duration' => $attributes['duration'] ?? null,
            'audio_url' => $attributes['audio_url'] ?? null,
            'video_url' => $attributes['video_url'] ?? null,
            'thumbnail' => $attributes['thumbnail'] ?? null,
            'cover_image' => $attributes['cover_image'] ?? null,
        ];
    }

    public function card(): array
    {
        return [
            'type' => 'podcast',
            'icon' => 'headphones',
            'title' => $this->title,
            'subtitle' => $this->topic,
            'occurred_at' => $this->occurred_at,
            'accent' => 'podcast',
            'meta' => [
                'media' => [
                    'id' => $this->id,
                    'title' => $this->title,
                    'audioUrl' => $this->audio_url,
                    'videoUrl' => $this->video_url,
                    'thumbnail' => $this->cover_image ?? $this->thumbnail,
                    'duration' => $this->duration,
                    'url' => $this->url(),
                ],
            ],
        ];
    }
}
