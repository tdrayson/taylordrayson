<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasStatus;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\LinkFaviconObserver;
use App\Observers\TimelineEntryObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy([TimelineEntryObserver::class, LinkFaviconObserver::class])]
#[Fillable([
    'occurred_at',
    'title',
    'slug',
    'excerpt',
    'content',
    'timezone',
    'status',
    'password',
])]
class Article extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasStatus, HasTags, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'content' => 'array',
        ];
    }

    public function slug(): string
    {
        return $this->getAttribute('slug');
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
