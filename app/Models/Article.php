<?php

namespace App\Models;

use App\Enums\ResponseKind;
use App\Enums\RsvpValue;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasResponse;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\LinkFaviconObserver;
use App\Observers\MentionObserver;
use App\Observers\TimelineEntryObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy([TimelineEntryObserver::class, LinkFaviconObserver::class, MentionObserver::class])]
#[Fillable([
    'occurred_at',
    'title',
    'slug',
    'excerpt',
    'content',
    'response_kind',
    'response_url',
    'response_title',
    'rsvp_value',
    'published',
    'timezone',
])]
class Article extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasResponse, HasTags, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'content' => 'array',
            'published' => 'boolean',
            'response_kind' => ResponseKind::class,
            'rsvp_value' => RsvpValue::class,
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
}
