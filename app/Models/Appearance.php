<?php

namespace App\Models;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\MediaData;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use App\Support\YouTube;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
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
class Appearance extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function slug(): string
    {
        return Str::slug($this->title);
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

    public function card(): CardData
    {
        return new CardData(
            type: 'appearance',
            icon: 'mic',
            title: $this->title,
            titleLabel: null,
            subtitle: $this->show_name,
            subtitleTokens: null,
            occurredAt: $this->occurred_at,
            accent: 'appearance',
            range: null,
            meta: CardMeta::media(MediaData::withSrcset(
                id: "appearance-{$this->id}",
                title: $this->title,
                audioUrl: $this->audio_url,
                videoUrl: $this->video_url,
                thumbnail: $this->thumbnailUrl(),
                srcset: $this->thumbnailSrcset(),
                duration: $this->duration,
                url: $this->url(),
            )),
        );
    }
}
