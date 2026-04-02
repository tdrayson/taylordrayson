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
    'title',
    'slug',
    'description',
    'long_description',
    'url',
    'github_url',
    'status',
    'featured',
    'tags',
    'started_at',
])]
class Project extends Model implements Timelineable
{
    use HasAssets, HasFactory, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'tags' => 'array',
            'featured' => 'boolean',
            'started_at' => 'datetime',
        ];
    }

    public function slug(): string
    {
        return $this->getAttribute('slug');
    }

    public function card(): array
    {
        return [
            'type' => 'project',
            'icon' => 'rocket',
            'title' => $this->title,
            'subtitle' => null,
            'occurred_at' => $this->occurred_at,
            'accent' => 'project',
            'meta' => [],
        ];
    }
}
