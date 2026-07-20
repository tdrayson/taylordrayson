<?php

namespace App\Models;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
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
    'description',
    'long_description',
    'url',
    'github_url',
    'status',
    'featured',
])]
class Project extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasTags, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'featured' => 'boolean',
        ];
    }

    public function slug(): string
    {
        return $this->getAttribute('slug');
    }

    public function card(): CardData
    {
        return new CardData(
            type: 'project',
            icon: 'rocket',
            title: $this->title,
            titleLabel: null,
            subtitle: $this->description,
            subtitleTokens: null,
            occurredAt: $this->occurred_at,
            accent: 'project',
            range: null,
            meta: CardMeta::empty(),
        );
    }
}
