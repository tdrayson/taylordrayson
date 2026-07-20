<?php

namespace App\Models;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\PhotoData;
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

    public function card(): CardData
    {
        $parts = array_filter([$this->venue_name, $this->city]);
        $photos = $this->galleryPhotos();

        return new CardData(
            type: 'event',
            icon: 'music',
            title: $this->name,
            titleLabel: null,
            subtitle: $parts ? implode(', ', $parts) : null,
            subtitleTokens: null,
            occurredAt: $this->occurred_at,
            accent: 'event',
            range: $this->dateRange(),
            meta: CardMeta::event(
                photos: array_map(
                    fn (array $photo): PhotoData => PhotoData::gallery($photo['src'], $photo['srcset'], $photo['full'], $photo['latitude'], $photo['longitude']),
                    $photos,
                ),
                // Fall back to the generated static location map only when there
                // is no photo to show instead (mirrors the activity route map).
                map: $photos === [] ? $this->getFirstMediaUrl('map') ?: null : null,
                // Dark twin of the same map, rendered by the frontend behind a
                // `dark:` class swap so the theme decides which PNG shows.
                mapDark: $photos === [] ? $this->getFirstMediaUrl('map_dark') ?: null : null,
            ),
        );
    }
}
