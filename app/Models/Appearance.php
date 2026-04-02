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
use Illuminate\Support\Str;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'occurred_at',
    'type',
    'title',
    'show_name',
    'url',
    'video_url',
    'description',
    'duration',
])]
class Appearance extends Model implements Timelineable
{
    use HasAssets, HasFactory, HasTimelineEntry;

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

    public function card(): array
    {
        return [
            'type' => 'appearance',
            'icon' => 'mic',
            'title' => $this->title,
            'subtitle' => $this->show_name,
            'occurred_at' => $this->occurred_at,
            'accent' => 'appearance',
            'meta' => [],
        ];
    }
}
