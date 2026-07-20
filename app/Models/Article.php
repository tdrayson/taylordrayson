<?php

namespace App\Models;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\PhotoData;
use App\Enums\TimelineType;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use App\Support\PortableText;
use App\Support\Text;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'occurred_at',
    'title',
    'slug',
    'excerpt',
    'content',
    'published',
    'timezone',
])]
class Article extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasTags, HasTimelineEntry;

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

    public function slug(): string
    {
        return $this->getAttribute('slug');
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

    public function card(): CardData
    {
        $cover = $this->coverPhoto();

        return new CardData(
            type: TimelineType::Article,
            icon: 'file-text',
            title: $this->title,
            titleLabel: null,
            subtitle: Text::excerpt(PortableText::plainText($this->content), 240) ?: $this->excerpt,
            subtitleTokens: null,
            occurredAt: $this->occurred_at,
            accent: 'article',
            range: null,
            meta: CardMeta::photos(
                $cover !== null ? [PhotoData::cover($cover['src'], $cover['srcset'], $cover['full'])] : [],
            ),
        );
    }
}
