<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The route block on a flight card: both endpoints, local departure/arrival
 * wall-clock strings, the distance in miles, duration in seconds, and the
 * operating airline (null when the relation was not eager-loaded).
 */
final readonly class RouteData implements Arrayable, JsonSerializable
{
    public function __construct(
        public RoutePoint $origin,
        public RoutePoint $destination,
        public ?string $depart,
        public ?string $arrive,
        public float|int|null $distance,
        public ?int $duration,
        public ?AirlineData $airline,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'origin' => $this->origin->toArray(),
            'destination' => $this->destination->toArray(),
            'depart' => $this->depart,
            'arrive' => $this->arrive,
            'distance' => $this->distance,
            'duration' => $this->duration,
            'airline' => $this->airline?->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
