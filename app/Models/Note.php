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
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'occurred_at',
    'content',
    'slug',
    'timezone',
])]
class Note extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasTags, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * The author-set slug when given, read from the raw attribute so unsaved
     * models fall back cleanly under strict attribute access.
     */
    public function slug(): string
    {
        return $this->attributes['slug'] ?? 'note';
    }

    public function card(): CardData
    {
        return new CardData(
            type: TimelineType::Note,
            icon: 'message-circle',
            title: Str::limit($this->content, 80),
            titleLabel: null,
            subtitle: null,
            subtitleTokens: null,
            occurredAt: $this->occurred_at,
            accent: 'note',
            range: null,
            meta: CardMeta::note(
                body: $this->content,
                photos: array_map(
                    fn (array $photo): PhotoData => PhotoData::gallery($photo['src'], $photo['srcset'], $photo['full'], $photo['latitude'], $photo['longitude']),
                    $this->galleryPhotos(),
                ),
            ),
        );
    }
}
