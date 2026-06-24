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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'occurred_at',
    'flight_number',
    'airline_icao',
    'origin_iata',
    'destination_iata',
    'distance_miles',
    'duration_min',
    'departure_timezone',
    'arrival_timezone',
    'co2_kg',
    'cabin_class',
    'reason',
    'meta',
])]
class Flight extends Model implements HasMedia, Timelineable
{
    use HasAttachments;
    use HasFactory;
    use HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'meta' => 'array',
            'duration_min' => 'integer',
            'co2_kg' => 'integer',
        ];
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
            'subtitle' => $this->distance_miles ? sprintf('%s mi · %s', number_format($this->distance_miles), $this->cabin_class) : null,
            'occurred_at' => $this->occurred_at,
            'accent' => 'flight',
            'meta' => [
                'route' => [
                    'origin' => ['iata' => $this->origin_iata, 'place' => $this->relationLoaded('origin') ? $this->origin?->place : null, 'name' => $this->relationLoaded('origin') ? $this->origin?->name : null, 'lat' => $this->relationLoaded('origin') ? $this->origin?->latitude : null, 'lng' => $this->relationLoaded('origin') ? $this->origin?->longitude : null],
                    'destination' => ['iata' => $this->destination_iata, 'place' => $this->relationLoaded('destination') ? $this->destination?->place : null, 'name' => $this->relationLoaded('destination') ? $this->destination?->name : null, 'lat' => $this->relationLoaded('destination') ? $this->destination?->latitude : null, 'lng' => $this->relationLoaded('destination') ? $this->destination?->longitude : null],
                    'depart' => data_get($this->meta, 'departed_actual') ?? data_get($this->meta, 'departed_scheduled'),
                    'arrive' => data_get($this->meta, 'arrived_actual'),
                    'distance' => $this->distance_miles,
                    'airline' => $this->relationLoaded('airline') && $this->airline ? ['name' => $this->airline->name, 'icon' => $this->airline->icon_url] : null,
                ],
            ],
        ];
    }
}
