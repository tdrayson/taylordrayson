<?php

namespace App\Models;

use App\Enums\CabinClass;
use App\Enums\FlightReason;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use App\Support\ZoneHistory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'occurred_at',
    'flight_number',
    'airline_icao',
    'origin_iata',
    'destination_iata',
    'distance',
    'duration',
    'departure_timezone',
    'arrival_timezone',
    'cabin_class',
    'reason',
    'meta',
])]
class Flight extends Model implements HasMedia, Timelineable
{
    use HasAttachments;
    use HasFactory;
    use HasTimelineEntry;

    /** @var list<string> */
    protected $appends = ['departed_local', 'arrived_local'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => FlightReason::class,
            'occurred_at' => 'datetime',
            'meta' => 'array',
            'duration' => 'integer',
            'distance' => 'integer',
            'cabin_class' => CabinClass::class,
        ];
    }

    /** A saved flight changes where the history says you were. */
    protected static function booted(): void
    {
        static::saved(fn () => app(ZoneHistory::class)->forget());
        static::deleted(fn () => app(ZoneHistory::class)->forget());
    }

    /**
     * Departure as a wall-clock string in the origin's local time: an explicit
     * actual/scheduled time from meta, otherwise occurred_at (stored origin-local).
     */
    protected function departedLocal(): Attribute
    {
        return Attribute::get(fn (): ?string => data_get($this->meta, 'departed_actual')
            ?? data_get($this->meta, 'departed_scheduled')
            ?? $this->occurred_at?->format('Y-m-d\TH:i'));
    }

    /**
     * Arrival as a wall-clock string in the destination's local time. Prefers an
     * explicit meta time; otherwise computes departure + duration across the two
     * timezones, so the landed time is correct and DST-aware (BST vs GMT, etc.).
     */
    protected function arrivedLocal(): Attribute
    {
        return Attribute::get(function (): ?string {
            $explicit = data_get($this->meta, 'arrived_actual') ?? data_get($this->meta, 'arrived_scheduled');

            if ($explicit) {
                return $explicit;
            }

            if (! $this->occurred_at || ! $this->duration || ! $this->departure_timezone || ! $this->arrival_timezone) {
                return null;
            }

            return $this->occurred_at
                ->copy()
                ->shiftTimezone($this->departure_timezone)
                ->addSeconds($this->duration)
                ->setTimezone($this->arrival_timezone)
                ->format('Y-m-d\TH:i');
        });
    }

    /**
     * The instant the flight landed, derived from the destination-local
     * arrival time so an explicit actual time is preferred over the computed
     * one.
     */
    public function arrivedAt(): ?CarbonImmutable
    {
        if (blank($this->arrived_local) || blank($this->arrival_timezone)) {
            return null;
        }

        return CarbonImmutable::parse($this->arrived_local, $this->arrival_timezone)->utc();
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class, 'airline_icao', 'icao_code');
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'origin_iata', 'iata_code');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'destination_iata', 'iata_code');
    }

    public function timezone(): ?string
    {
        return $this->departure_timezone;
    }

    public function slug(): string
    {
        return strtolower("{$this->origin_iata}-{$this->destination_iata}");
    }
}
