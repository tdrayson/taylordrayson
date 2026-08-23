<?php

namespace App\Queries;

use App\Data\AirlineData;
use App\Data\FlightMapEntry;
use App\Data\FlightMapStats;
use App\Data\RoutePoint;
use App\Models\Flight;

/**
 * Computes the full flight globe map payload: one entry per geocoded flight,
 * the years they span, and all-time plus per-year stats buckets.
 */
final class FlightMapData
{
    /**
     * @return array{entries: list<FlightMapEntry>, years: list<int>, stats: array<string, FlightMapStats>}
     */
    public function __invoke(): array
    {
        $flights = Flight::query()
            ->with(['origin', 'destination', 'airline'])
            ->orderByDesc('occurred_at')
            ->get()
            ->filter(fn (Flight $flight): bool => $flight->origin?->latitude !== null && $flight->destination?->latitude !== null);

        $entries = $flights->map(fn (Flight $flight): FlightMapEntry => $this->entry($flight))->values()->all();

        $years = collect($entries)->pluck('year')->unique()->sortDesc()->values()->all();

        $stats = ['all' => $this->stats($flights->values()->all())];

        foreach ($years as $year) {
            $stats[(string) $year] = $this->stats($flights->filter(
                fn (Flight $flight): bool => $flight->occurred_at->year === $year
            )->values()->all());
        }

        return ['entries' => $entries, 'years' => $years, 'stats' => $stats];
    }

    /**
     * Builds a map entry with route endpoints and airline exactly as
     * FlightCard does, except distance stays raw metres for the map.
     */
    private function entry(Flight $flight): FlightMapEntry
    {
        return new FlightMapEntry(
            id: $flight->id,
            occurredAt: $flight->occurred_at->toIso8601String(),
            year: $flight->occurred_at->year,
            flightNumber: $flight->flight_number,
            airline: $flight->relationLoaded('airline') && $flight->airline
                ? new AirlineData(
                    name: $flight->airline->name,
                    icon: $flight->airline->icon_url,
                    number: trim(($flight->airline->iata_code ?: $flight->airline_icao).' '.$flight->flight_number),
                )
                : null,
            origin: new RoutePoint(
                iata: $flight->origin_iata,
                place: $flight->relationLoaded('origin') ? $flight->origin?->place : null,
                name: $flight->relationLoaded('origin') ? $flight->origin?->name : null,
                lat: $flight->relationLoaded('origin') ? $flight->origin?->latitude : null,
                lng: $flight->relationLoaded('origin') ? $flight->origin?->longitude : null,
            ),
            destination: new RoutePoint(
                iata: $flight->destination_iata,
                place: $flight->relationLoaded('destination') ? $flight->destination?->place : null,
                name: $flight->relationLoaded('destination') ? $flight->destination?->name : null,
                lat: $flight->relationLoaded('destination') ? $flight->destination?->latitude : null,
                lng: $flight->relationLoaded('destination') ? $flight->destination?->longitude : null,
            ),
            distance: (int) $flight->distance,
            duration: $flight->duration,
            cabinClass: $flight->cabin_class?->value,
            aircraft: $flight->meta['aircraft'] ?? null,
            href: $flight->url(),
        );
    }

    /**
     * Aggregates one stats bucket (all-time or a single year) from its flights.
     *
     * @param  list<Flight>  $flights
     */
    private function stats(array $flights): FlightMapStats
    {
        $flights = collect($flights);

        $airports = $flights
            ->flatMap(fn (Flight $flight): array => [$flight->origin_iata, $flight->destination_iata])
            ->filter()
            ->unique();

        $airlines = $flights->pluck('airline_icao')->filter()->unique();

        $longest = $flights->sortByDesc('distance')->first();

        $routeCounts = $flights->countBy(fn (Flight $flight): string => collect([$flight->origin_iata, $flight->destination_iata])->sort()->implode('-'));
        $topRouteKey = $routeCounts->sortDesc()->keys()->first();

        $aircraftCounts = $flights->pluck('meta.aircraft')->filter()->countBy();
        $topAircraft = $aircraftCounts->sortDesc()->keys()->first();

        return new FlightMapStats(
            flights: $flights->count(),
            distance: (int) $flights->sum('distance'),
            duration: (int) $flights->sum('duration'),
            airports: $airports->count(),
            airlines: $airlines->count(),
            longestRoute: $longest ? "{$longest->origin_iata} to {$longest->destination_iata}" : null,
            longestDistance: $longest ? (int) $longest->distance : 0,
            topRoute: $topRouteKey ? str_replace('-', ' to ', $topRouteKey) : null,
            topRouteCount: $topRouteKey ? $routeCounts[$topRouteKey] : 0,
            topAircraft: $topAircraft,
            topAircraftCount: $topAircraft ? $aircraftCounts[$topAircraft] : 0,
        );
    }
}
