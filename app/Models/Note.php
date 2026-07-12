<?php

namespace App\Models;

use App\Contracts\DefinesContentSchema;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasFlatFile;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Database\Factories\NoteFactory;
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
    'content',
    'slug',
    'timezone',
])]
class Note extends Model implements DefinesContentSchema, HasMedia, Timelineable
{
    /** @use HasFactory<NoteFactory> */
    use HasAttachments, HasFactory, HasFlatFile, HasTags, HasTimelineEntry;

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
        $table->string('slug')->nullable();
        $table->text('content');
        $table->timestamps();
    }

    /**
     * The author-set slug when given, read from the raw attribute so unsaved
     * models fall back cleanly under strict attribute access.
     */
    public function slug(): string
    {
        return $this->attributes['slug'] ?? 'note';
    }

    public function flatFileType(): string
    {
        return 'note';
    }

    public function flatFileBody(): string
    {
        return (string) $this->content;
    }

    public function card(): array
    {
        return [
            'type' => 'note',
            'icon' => 'message-circle',
            'title' => Str::limit($this->content, 80),
            'subtitle' => null,
            'occurred_at' => $this->occurred_at,
            'accent' => 'note',
            'meta' => [
                'body' => $this->content,
                'photos' => $this->galleryPhotos(),
            ],
        ];
    }
}
