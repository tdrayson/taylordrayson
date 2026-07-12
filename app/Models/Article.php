<?php

namespace App\Models;

use App\Contracts\DefinesContentSchema;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasFlatFile;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use App\Support\PortableText;
use App\Support\Text;
use Database\Factories\ArticleFactory;
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
    'title',
    'slug',
    'excerpt',
    'content',
    'published',
    'timezone',
])]
class Article extends Model implements DefinesContentSchema, HasMedia, Timelineable
{
    /** @use HasFactory<ArticleFactory> */
    use HasAttachments, HasFactory, HasFlatFile, HasTags, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'content' => 'array',
            'published' => 'boolean',
        ];
    }

    public static function schema(Blueprint $table): void
    {
        $table->id();
        $table->ulid('ulid')->nullable()->unique();
        $table->timestamp('occurred_at')->index();
        $table->string('timezone')->nullable();
        $table->string('title');
        $table->string('slug');
        $table->text('excerpt')->nullable();
        $table->text('content');
        $table->boolean('published')->default(false);
        $table->timestamps();
    }

    public function slug(): string
    {
        return $this->getAttribute('slug');
    }

    public function flatFileType(): string
    {
        return 'article';
    }

    public function flatFileExtension(): string
    {
        return 'json';
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
            'title' => $attributes['title'] ?? null,
            'excerpt' => $attributes['excerpt'] ?? null,
            'published' => (bool) ($attributes['published'] ?? false),
            'content' => $this->content,
        ];
    }

    /**
     * Read from the raw attribute so unsaved models resolve to false rather
     * than throwing under strict attribute access.
     */
    public function shouldAppearOnTimeline(): bool
    {
        return (bool) ($this->attributes['published'] ?? false);
    }

    /**
     * The featured image in the card/lightbox payload shape shared with
     * activity photos, or null when no cover is attached.
     *
     * @return array{src: string, srcset: ?string, full: string}|null
     */
    public function coverPhoto(): ?array
    {
        $media = $this->getFirstMedia('cover');

        if ($media === null) {
            return null;
        }

        return [
            'src' => $media->getUrl('card'),
            'srcset' => $media->getSrcset('card') ?: null,
            'full' => $media->getUrl(),
        ];
    }

    public function card(): array
    {
        return [
            'type' => 'article',
            'icon' => 'file-text',
            'title' => $this->title,
            'subtitle' => Text::excerpt(PortableText::plainText($this->content), 240) ?: $this->excerpt,
            'occurred_at' => $this->occurred_at,
            'accent' => 'article',
            'meta' => [
                'photos' => array_values(array_filter([$this->coverPhoto()])),
            ],
        ];
    }
}
