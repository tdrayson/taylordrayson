<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlightResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occurred_at' => $this->occurred_at?->toDateTimeString(),
            'flight_number' => $this->flight_number,
            'airline' => $this->whenLoaded('airline', fn () => [
                'icao' => $this->airline->icao_code,
                'iata' => $this->airline->iata_code,
                'name' => $this->airline->name,
            ]),
            'origin' => $this->whenLoaded('origin', fn () => [
                'iata' => $this->origin->iata_code,
                'name' => $this->origin->name,
            ]),
            'destination' => $this->whenLoaded('destination', fn () => [
                'iata' => $this->destination->iata_code,
                'name' => $this->destination->name,
            ]),
            'duration' => $this->duration,
            'distance' => $this->distance,
            'cabin_class' => $this->cabin_class?->value,
            'reason' => $this->reason?->value,
            'departure_timezone' => $this->departure_timezone,
            'arrival_timezone' => $this->arrival_timezone,
            'departed_local' => $this->departed_local,
            'arrived_local' => $this->arrived_local,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
