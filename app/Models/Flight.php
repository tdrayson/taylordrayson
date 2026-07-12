<?php

namespace App\Models;

use App\Contracts\DefinesContentSchema;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasFlatFile;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use App\Support\Distance;
use Database\Factories\FlightFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'ulid',
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
class Flight extends Model implements DefinesContentSchema, HasMedia, Timelineable
{
    /** @use HasFactory<FlightFactory> */
    use HasAttachments;

    use HasFactory;
    use HasFlatFile;
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
        ];
    }

    public static function schema(Blueprint $table): void
    {
        $table->id();
        $table->ulid('ulid')->nullable()->unique();
        $table->timestamp('occurred_at')->index();
        $table->string('flight_number');
        $table->string('airline_icao')->nullable();
        $table->string('origin_iata')->nullable();
        $table->string('destination_iata')->nullable();
        $table->integer('distance')->nullable();
        $table->integer('duration')->nullable();
        $table->string('departure_timezone')->nullable();
        $table->string('arrival_timezone')->nullable();
        $table->string('cabin_class')->nullable();
        $table->string('reason')->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
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

    public function flatFileType(): string
    {
        return 'flight';
    }

    /**
     * @return list<string>
     */
    public function flatFilePathAttributes(): array
    {
        return ['occurred_at', 'origin_iata', 'destination_iata'];
    }

    public function flatFileBaseSlug(bool $original = false): string
    {
        if ($original) {
            $origin = $this->getOriginal('origin_iata') ?? $this->origin_iata;
            $destination = $this->getOriginal('destination_iata') ?? $this->destination_iata;

            return strtolower("{$origin}-{$destination}");
        }

        return $this->slug();
    }

    public function flatFileBody(): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function flatFileMeta(): array
    {
        $attributes = $this->getAttributes();

        return [
            'flight_number' => $attributes['flight_number'] ?? null,
            'airline_icao' => $attributes['airline_icao'] ?? null,
            'origin_iata' => $attributes['origin_iata'] ?? null,
            'destination_iata' => $attributes['destination_iata'] ?? null,
            'distance' => $attributes['distance'] ?? null,
            'duration' => $attributes['duration'] ?? null,
            'departure_timezone' => $attributes['departure_timezone'] ?? null,
            'arrival_timezone' => $attributes['arrival_timezone'] ?? null,
            'cabin_class' => $attributes['cabin_class'] ?? null,
            'reason' => $attributes['reason'] ?? null,
            'meta' => $this->meta,
        ];
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
            'subtitle' => $this->distance ? sprintf('%s mi, %s', number_format(Distance::miles($this->distance)), $this->cabin_class) : null,
            // Raw metres (not Distance::miles) so FeedItem.vue converts via useFormat and
            // reacts to the visitor's unit toggle.
            'subtitleTokens' => $this->distance
                ? [['t' => 'dist', 'm' => (int) $this->distance, 'p' => 0], ['t' => 'text', 'v' => $this->cabin_class]]
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
            ],
        ];
    }
}
