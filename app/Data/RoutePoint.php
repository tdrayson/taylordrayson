<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One end (origin or destination) of a flight route: the IATA code always
 * known, with place/name/coordinates filled only when the Airport relation
 * was eager-loaded (the entry page); null on the feed to avoid an N+1.
 */
final readonly class RoutePoint implements Arrayable, JsonSerializable
{
    public function __construct(
        public ?string $iata,
        public ?string $place,
        public ?string $name,
        public ?float $lat,
        public ?float $lng,
    ) {}

    /**
     * @return array{iata: ?string, place: ?string, name: ?string, lat: ?float, lng: ?float}
     */
    public function toArray(): array
    {
        return [
            'iata' => $this->iata,
            'place' => $this->place,
            'name' => $this->name,
            'lat' => $this->lat,
            'lng' => $this->lng,
        ];
    }

    /**
     * @return array{iata: ?string, place: ?string, name: ?string, lat: ?float, lng: ?float}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
