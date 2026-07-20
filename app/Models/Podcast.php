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
}
