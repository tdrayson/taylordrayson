<?php

namespace App\Actions\Flights;

use App\Jobs\GenerateEntryMap;
use App\Models\Flight;
use Illuminate\Support\Arr;

class CreateFlight
{
    /**
     * Idempotent create: retried submissions of the same flight update in
     * place instead of duplicating, keyed on the flight's natural identity.
     * Timezones omitted by the caller fall back to the home timezone.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{flight: Flight, created: bool}
     */
    public function __invoke(array $attributes): array
    {
        $attributes['departure_timezone'] ??= config('app.home_timezone');
        $attributes['arrival_timezone'] ??= config('app.home_timezone');

        $key = [
            'occurred_at' => $attributes['occurred_at'],
            'flight_number' => $attributes['flight_number'],
            'origin_iata' => $attributes['origin_iata'],
            'destination_iata' => $attributes['destination_iata'],
        ];

        $flight = Flight::updateOrCreate($key, Arr::except($attributes, array_keys($key)));
        $created = $flight->wasRecentlyCreated;

        // Drawn on the queue so a slow or failed Mapbox call neither holds up
        // the response nor leaves the flight permanently unmapped. The job
        // no-ops if the arc is already there.
        if ($created) {
            GenerateEntryMap::dispatch($flight);
        }

        return ['flight' => $flight->refresh(), 'created' => $created];
    }
}
