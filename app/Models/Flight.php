<?php

namespace App\Models;

use App\Enums\CabinClass;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use App\Support\Distance;
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
            'occurred_at' => 'datetime',
            'meta' => 'array',
            'duration' => 'integer',
            'distance' => 'integer',
            'cabin_class' => CabinClass::class,
        ];
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

    /**
     * Route title using city names when the airport relations are loaded
     * (the entry page), falling back to IATA codes otherwise (the feed).
     */
    private function routeTitle(): string
    {
        $origin = ($this->relationLoaded('origin') ? $this->origin?->place : null) ?? $this->origin_iata;
        $destination = ($this->relationLoaded('destination') ? $this->destination?->place : null) ?? $this->destination_iata;

        return "{$origin} → {$destination}";
    }

    public function card(): array
    {
        return [
            'type' => 'flight',
            'icon' => 'plane',
            'title' => $this->routeTitle(),
            'subtitle' => $this->distance ? sprintf('%s mi, %s', number_format(Distance::miles($this->distance)), $this->cabin_class?->label()) : null,
            // Raw metres (not Distance::miles) so FeedItem.vue converts via useFormat and
            // reacts to the visitor's unit toggle.
            'subtitleTokens' => $this->distance
                ? [['t' => 'dist', 'm' => (int) $this->distance, 'p' => 0], ['t' => 'text', 'v' => $this->cabin_class?->label()]]
                : null,
            'occurred_at' => $this->occurred_at,
            'accent' => 'flight',
            'meta' => [
                'route' => [
                    'origin' => ['iata' => $this->origin_iata, 'place' => $this->relationLoaded('origin') ? $this->origin?->place : null, 'name' => $this->relationLoaded('origin') ? $this->origin?->name : null, 'lat' => $this->relationLoaded('origin') ? $this->origin?->latitude : null, 'lng' => $this->relationLoaded('origin') ? $this->origin?->longitude : null],
                    'destination' => ['iata' => $this->destination_iata, 'place' => $this->relationLoaded('destination') ? $this->destination?->place : null, 'name' => $this->relationLoaded('destination') ? $this->destination?->name : null, 'lat' => $this->relationLoaded('destination') ? $this->destination?->latitude : null, 'lng' => $this->relationLoaded('destination') ? $this->destination?->longitude : null],
                    'depart' => $this->departed_local,
                    'arrive' => $this->arrived_local,
                    'distance' => Distance::miles($this->distance),
                    'duration' => $this->duration,
                    'airline' => $this->relationLoaded('airline') && $this->airline ? ['name' => $this->airline->name, 'icon' => $this->airline->icon_url, 'number' => trim(($this->airline->iata_code ?: $this->airline_icao).' '.$this->flight_number)] : null,
                ],
                'map' => $this->getFirstMediaUrl('map') ?: null,
                'mapDark' => $this->getFirstMediaUrl('map_dark') ?: null,
            ],
        ];
    }
}
