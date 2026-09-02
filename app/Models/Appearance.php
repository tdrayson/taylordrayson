<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasSubjects;
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
    'timezone',
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
    use HasAttachments, HasFactory, HasSubjects, HasTimelineEntry;

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
}
