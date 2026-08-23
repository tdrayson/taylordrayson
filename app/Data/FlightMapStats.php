<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Summary stats for a flight-map bucket (all-time or one year): totals,
 * counts, and the standout route/aircraft for that bucket.
 */
final readonly class FlightMapStats implements Arrayable, JsonSerializable
{
    public function __construct(
        public int $flights,
        public int $distance,
        public int $duration,
        public int $airports,
        public int $airlines,
        public ?string $longestRoute,
        public int $longestDistance,
        public ?string $topRoute,
        public int $topRouteCount,
        public ?string $topAircraft,
        public int $topAircraftCount,
    ) {}

    /**
     * @return array{flights: int, distance: int, duration: int, airports: int, airlines: int, longestRoute: ?string, longestDistance: int, topRoute: ?string, topRouteCount: int, topAircraft: ?string, topAircraftCount: int}
     */
    public function toArray(): array
    {
        return [
            'flights' => $this->flights,
            'distance' => $this->distance,
            'duration' => $this->duration,
            'airports' => $this->airports,
            'airlines' => $this->airlines,
            'longestRoute' => $this->longestRoute,
            'longestDistance' => $this->longestDistance,
            'topRoute' => $this->topRoute,
            'topRouteCount' => $this->topRouteCount,
            'topAircraft' => $this->topAircraft,
            'topAircraftCount' => $this->topAircraftCount,
        ];
    }

    /**
     * @return array{flights: int, distance: int, duration: int, airports: int, airlines: int, longestRoute: ?string, longestDistance: int, topRoute: ?string, topRouteCount: int, topAircraft: ?string, topAircraftCount: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
