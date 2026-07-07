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
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'occurred_at',
    'ends_at',
    'all_day',
    'type',
    'name',
    'company',
    'venue_name',
    'city',
    'country',
    'latitude',
    'longitude',
    'url',
    'description',
    'timezone',
    'meta',
])]
class Event extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function slug(): string
    {
        return Str::slug($this->name);
    }

    /**
     * The event's day span for multi-day display, or null when it is a single
     * day. `label` compacts a same-month range to "2-4 Jun 2022" and a
     * cross-month range to "30 Jun - 2 Jul 2022".
     *
     * @return array{start: string, end: string, days: int, label: string}|null
     */
    public function dateRange(): ?array
    {
        if ($this->ends_at === null || $this->ends_at->toDateString() === $this->occurred_at->toDateString()) {
            return null;
        }

        $start = $this->occurred_at->copy();
        $end = $this->ends_at->copy();
        $days = $start->startOfDay()->diffInDays($end->startOfDay()) + 1;

        $label = $start->format('n') === $end->format('n')
            ? $start->format('j').'-'.$end->format('j M Y')
            : $start->format('j M').' - '.$end->format('j M Y');

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'days' => (int) $days,
            'label' => $label,
        ];
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
            'range' => $this->dateRange(),
            'meta' => [],
        ];
    }
}
