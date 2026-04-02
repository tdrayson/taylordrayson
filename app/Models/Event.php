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
    'name',
    'venue_name',
    'address',
    'city',
    'country',
    'latitude',
    'longitude',
    'ticket_price',
    'notes',
])]
class Event extends Model implements Timelineable
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
        return Str::slug($this->name);
    }

    public function card(): array
    {
        $parts = array_filter([$this->venue_name, $this->city]);

        return [
            'type' => 'event',
            'icon' => 'music',
            'title' => $this->name,
            'subtitle' => $parts ? implode(', ', $parts) : null,
            'occurred_at' => $this->occurred_at,
            'accent' => 'event',
            'meta' => [],
        ];
    }
}
