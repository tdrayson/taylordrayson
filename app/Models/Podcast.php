<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
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
    'season_number',
    'episode_number',
    'topic',
    'show_notes',
    'transcript',
    'duration',
    'audio_url',
    'video_url',
    'thumbnail',
    'cover_image',
])]
class Podcast extends Model implements HasMedia, Timelineable
{
    use HasAttachments;
    use HasFactory;
    use HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'duration' => 'integer',
        ];
    }

    public function getTitleAttribute(): string
    {
        return "Season {$this->season_number}, Episode {$this->episode_number}";
    }

    public function slug(): string
    {
        return "tww-s{$this->season_number}-e{$this->episode_number}";
    }

    /**
     * The episode audio: the mirrored copy once stored, otherwise the publisher's
     * URL, so the player keeps working while the archive fills up.
     */
    public function audioSrc(): ?string
    {
        return $this->getFirstMediaUrl('audio') ?: $this->audio_url;
    }

    /**
     * The wide 16:9 episode art, mirrored copy first. Serves the optimised
     * `card` conversion rather than the stored original, as covers elsewhere do.
     */
    public function wideArtworkSrc(): ?string
    {
        return $this->getFirstMediaUrl('cover', 'card') ?: $this->cover_image;
    }

    /** The square episode art (what the audio player shows), mirrored copy first. */
    public function squareArtworkSrc(): ?string
    {
        return $this->getFirstMediaUrl('artwork', 'card') ?: $this->thumbnail;
    }
}
