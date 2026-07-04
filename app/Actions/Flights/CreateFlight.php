<?php

namespace App\Actions\Flights;

use App\Models\Flight;
use Illuminate\Support\Arr;

class CreateFlight
{
    /**
     * Idempotent create: retried submissions of the same flight update in
     * place instead of duplicating, keyed on the flight's natural identity.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{flight: Flight, created: bool}
     */
    public function __invoke(array $attributes): array
    {
        $key = [
            'occurred_at' => $attributes['occurred_at'],
            'flight_number' => $attributes['flight_number'],
            'origin_iata' => $attributes['origin_iata'],
            'destination_iata' => $attributes['destination_iata'],
        ];

        $flight = Flight::query()->where($key)->first();
        $created = $flight === null;

        $flight = Flight::updateOrCreate($key, Arr::except($attributes, array_keys($key)));

        return ['flight' => $flight->refresh(), 'created' => $created];
    }
}
