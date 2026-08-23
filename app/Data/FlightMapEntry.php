<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One flight plotted on the globe map: route endpoints, airline, and the raw
 * metrics (metres, seconds) the Vue layer formats via useFormat.
 */
final readonly class FlightMapEntry implements Arrayable, JsonSerializable
{
    public function __construct(
        public int $id,
        public string $occurredAt,
        public int $year,
        public string $flightNumber,
        public ?AirlineData $airline,
        public RoutePoint $origin,
        public RoutePoint $destination,
        public int $distance,
        public ?int $duration,
        public ?string $cabinClass,
        public ?string $aircraft,
        public string $href,
    ) {}

    /**
     * @return array{id: int, occurredAt: string, year: int, flightNumber: string, airline: ?array{name: ?string, icon: ?string, number: string}, origin: array{iata: ?string, place: ?string, name: ?string, lat: ?float, lng: ?float}, destination: array{iata: ?string, place: ?string, name: ?string, lat: ?float, lng: ?float}, distance: int, duration: ?int, cabinClass: ?string, aircraft: ?string, href: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'occurredAt' => $this->occurredAt,
            'year' => $this->year,
            'flightNumber' => $this->flightNumber,
            'airline' => $this->airline?->toArray(),
            'origin' => $this->origin->toArray(),
            'destination' => $this->destination->toArray(),
            'distance' => $this->distance,
            'duration' => $this->duration,
            'cabinClass' => $this->cabinClass,
            'aircraft' => $this->aircraft,
            'href' => $this->href,
        ];
    }

    /**
     * @return array{id: int, occurredAt: string, year: int, flightNumber: string, airline: ?array{name: ?string, icon: ?string, number: string}, origin: array{iata: ?string, place: ?string, name: ?string, lat: ?float, lng: ?float}, destination: array{iata: ?string, place: ?string, name: ?string, lat: ?float, lng: ?float}, distance: int, duration: ?int, cabinClass: ?string, aircraft: ?string, href: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
