<?php

namespace App\Models;

use App\Data\RangeData;
use App\Models\Concerns\HasTags;
use App\Support\DateRange;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A named window of time. A trip owns no content: its page is a query over the
 * timeline entries already falling inside its window, so nothing is filed into
 * it and nothing is duplicated.
 *
 * `starts_at` and `ends_at` are local wall-clock times in `timezone`, matching
 * how every timeline model stores its own `occurred_at`.
 */
#[Fillable([
    'title',
    'slug',
    'starts_at',
    'ends_at',
    'timezone',
])]
class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory, HasTags;

    /**
     * Derive the slug from the title when one was not supplied, so a trip
     * saved without an explicit slug still has a working URL.
     */
    protected static function booted(): void
    {
        static::saving(function (Trip $trip): void {
            if (blank($trip->slug)) {
                $trip->slug = Str::slug($trip->title);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * The trip's window, formatted the way every other multi-day span on the
     * site is. A trip is always a range, so unlike an event this never returns
     * null for a single day.
     */
    public function dateRange(): RangeData
    {
        return DateRange::for($this->starts_at, $this->ends_at);
    }

    /**
     * The trip's length in days, counting both the first and last day.
     */
    public function days(): int
    {
        return $this->dateRange()->days;
    }

    public function url(): string
    {
        return "/trips/{$this->slug}";
    }
}
