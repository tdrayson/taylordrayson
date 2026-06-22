<?php

namespace App\Models;

use App\Models\Concerns\HasAssets;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    'youtube_url',
    'thumbnail',
    'cover_image',
])]
class Podcast extends Model implements Timelineable
{
    use HasAssets;
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

    public function card(): array
    {
        return [
            'type' => 'podcast',
            'icon' => 'headphones',
            'title' => $this->title,
            'subtitle' => $this->topic,
            'occurred_at' => $this->occurred_at,
            'accent' => 'podcast',
            'meta' => [
                'media' => [
                    'id' => $this->id,
                    'title' => $this->title,
                    'audioUrl' => $this->audio_url,
                    'youtubeUrl' => $this->youtube_url,
                    'thumbnail' => $this->thumbnail,
                    'duration' => $this->duration,
                    'url' => $this->url(),
                ],
            ],
        ];
    }
}
