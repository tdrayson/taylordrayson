<?php

namespace App\Models;

use App\Contracts\DefinesContentSchema;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasFlatFile;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use App\Support\YouTube;
use Database\Factories\AppearanceFactory;
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
    'show_name',
    'url',
    'video_url',
    'audio_url',
    'description',
    'duration',
])]
class Appearance extends Model implements DefinesContentSchema, HasMedia, Timelineable
{
    /** @use HasFactory<AppearanceFactory> */
    use HasAttachments, HasFactory, HasFlatFile, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
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
        $table->string('show_name')->nullable();
        $table->string('url')->nullable();
        $table->string('video_url')->nullable();
        $table->string('audio_url')->nullable();
        $table->text('description')->nullable();
        $table->integer('duration')->nullable();
        $table->timestamps();
    }

    public function slug(): string
    {
        return Str::slug($this->title);
    }

    public function flatFileType(): string
    {
        return 'appearance';
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
            'title' => $attributes['title'] ?? null,
            'show_name' => $attributes['show_name'] ?? null,
            'url' => $attributes['url'] ?? null,
            'video_url' => $attributes['video_url'] ?? null,
            'audio_url' => $attributes['audio_url'] ?? null,
            'duration' => $attributes['duration'] ?? null,
        ];
    }

    /**
     * The thumbnail to show for this appearance: the optimised cover conversion
     * when a cover has been stored, otherwise the video's YouTube thumbnail.
     */
    public function thumbnailUrl(): ?string
    {
        $cover = $this->getFirstMediaUrl('cover', 'card');

        return $cover !== '' ? $cover : YouTube::thumbnail($this->video_url);
    }

    /**
     * The responsive srcset for the stored cover, or null when there is no cover
     * (the derived YouTube thumbnail is a single fixed size).
     */
    public function thumbnailSrcset(): ?string
    {
        $srcset = $this->getFirstMedia('cover')?->getSrcset('card');

        return $srcset !== null && $srcset !== '' ? $srcset : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function card(): array
    {
        return [
            'type' => 'appearance',
            'icon' => 'mic',
            'title' => $this->title,
            'subtitle' => $this->show_name,
            'occurred_at' => $this->occurred_at,
            'accent' => 'appearance',
            'meta' => [
                'media' => [
                    'id' => "appearance-{$this->id}",
                    'title' => $this->title,
                    'audioUrl' => $this->audio_url,
                    'videoUrl' => $this->video_url,
                    'thumbnail' => $this->thumbnailUrl(),
                    'srcset' => $this->thumbnailSrcset(),
                    'duration' => $this->duration,
                    'url' => $this->url(),
                ],
            ],
        ];
    }
}
