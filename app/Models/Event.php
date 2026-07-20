<?php

namespace App\Models;

use App\Data\RangeData;
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
    'organiser',
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
     * day. `label` is the compact card form ("2-4 Jun 2022"); `long` is the
     * spelled-out detail form ("4th to 6th June 2026").
     */
    public function dateRange(): ?RangeData
    {
        if ($this->ends_at === null || $this->ends_at->toDateString() === $this->occurred_at->toDateString()) {
            return null;
        }

        $start = $this->occurred_at->copy();
        $end = $this->ends_at->copy();
        $days = $start->startOfDay()->diffInDays($end->startOfDay()) + 1;

        $sameMonth = $start->format('n') === $end->format('n') && $start->format('Y') === $end->format('Y');
        $sameYear = $start->format('Y') === $end->format('Y');

        $label = $sameMonth
            ? $start->format('j').'-'.$end->format('j M Y')
            : $start->format('j M').' - '.$end->format('j M Y');

        if ($sameMonth) {
            $long = $start->format('jS').' to '.$end->format('jS F Y');
        } elseif ($sameYear) {
            $long = $start->format('jS F').' to '.$end->format('jS F Y');
        } else {
            $long = $start->format('jS F Y').' to '.$end->format('jS F Y');
        }

        return new RangeData(
            start: $start->toDateString(),
            end: $end->toDateString(),
            days: (int) $days,
            label: $label,
            long: $long,
        );
    }
}
