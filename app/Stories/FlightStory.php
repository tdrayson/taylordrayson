<?php

namespace App\Stories;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Support\OgMeta;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Computes the figures and chart series behind the flights data story from live
 * flight records, joined to the airports lookup for places and countries, so the
 * narrative stays current on every refresh.
 *
 * Totals are framed as "the flights I can still find a record of": the early
 * years are sparse because the data is lost, not because no flights happened.
 */
class FlightStory implements Story
{
    /** The home airport that anchors most trips. */
    private const HUB = 'LGW';

    /** ICAO codes of the low-cost carriers, for the budget-travel angle. */
    private const BUDGET_ICAO = ['EZY', 'WUK', 'WZZ', 'RYR', 'RUK', 'EJU'];

    public function slug(): string
    {
        return 'flights';
    }

    public function component(): string
    {
        return 'Stories/Flights';
    }

    /**
     * @return array<string, mixed>
     */
    public function og(): array
    {
        return OgMeta::flightStory();
    }

    /**
     * @return array{slug: string, type: string, title: string, description: string, accent: string}
     */
    public function card(): array
    {
        $og = $this->og();

        return [
            'slug' => $this->slug(),
            'type' => 'flight',
            'title' => $og['heading'],
            'description' => $og['description'],
            'accent' => $og['accent'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $flights = Flight::query()->orderBy('occurred_at')->get();

        if ($flights->isEmpty()) {
            return ['hasData' => false];
        }

        $airports = $this->airports($flights);

        return [
            'hasData' => true,
            'kpis' => $this->kpis($flights, $airports),
            'byYear' => $this->byYear($flights)->values()->all(),
            'extremes' => $this->extremes($flights),
            'hub' => $this->hub($flights),
            'airports' => $this->topAirports($flights),
            'fleet' => $this->fleet($flights),
            'seats' => $this->seats($flights),
            'budget' => $this->budget($flights),
            'purpose' => $this->purpose($flights),
            'gaps' => $this->gaps($flights),
            'routes' => $this->routes($flights, $airports),
            'countries' => $this->countries($flights, $airports),
            'airportNames' => $airports->map(fn (Airport $airport): ?string => $airport->name)->filter()->all(),
        ];
    }

    /**
     * The airports lookup keyed by IATA, for place names and countries.
     *
     * @return Collection<string, Airport>
     */
    private function airports(Collection $flights): Collection
    {
        $codes = $flights->pluck('origin_iata')
            ->merge($flights->pluck('destination_iata'))
            ->filter()
            ->unique();

        return Airport::query()->whereIn('iata_code', $codes)->get()->keyBy('iata_code');
    }

    /**
     * Every flight as an origin/destination pair with coordinates, for the map.
     *
     * @param  Collection<string, Airport>  $airports
     * @return array<int, array<string, mixed>>
     */
    private function routes(Collection $flights, Collection $airports): array
    {
        return $flights->map(function (Flight $flight) use ($airports): ?array {
            $origin = $airports->get($flight->origin_iata);
            $destination = $airports->get($flight->destination_iata);

            if (! $origin || ! $destination || $origin->latitude === null || $destination->latitude === null) {
                return null;
            }

            return [
                'origin' => ['lat' => (float) $origin->latitude, 'lng' => (float) $origin->longitude, 'iata' => $flight->origin_iata],
                'destination' => ['lat' => (float) $destination->latitude, 'lng' => (float) $destination->longitude, 'iata' => $flight->destination_iata],
            ];
        })->filter()->values()->all();
    }

    /**
     * The distinct country codes I've touched, sorted, for the flags grid.
     *
     * @param  Collection<string, Airport>  $airports
     * @return array<int, string>
     */
    private function countries(Collection $flights, Collection $airports): array
    {
        return $flights
            ->flatMap(fn (Flight $flight): array => [$flight->origin_iata, $flight->destination_iata])
            ->map(fn (?string $iata): ?string => $airports->get($iata)?->country)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<string, Airport>  $airports
     * @return array<string, mixed>
     */
    private function kpis(Collection $flights, Collection $airports): array
    {
        $codes = $flights->pluck('origin_iata')->merge($flights->pluck('destination_iata'))->filter()->unique();
        $countries = $airports->pluck('country')->filter()->unique();
        $first = $flights->first();
        $last = $flights->last();

        $domestic = $flights->filter(function (Flight $flight) use ($airports): bool {
            $origin = $airports->get($flight->origin_iata);
            $destination = $airports->get($flight->destination_iata);

            return $origin && $destination && $origin->country === $destination->country;
        })->count();

        $miles = (int) $flights->sum('distance_miles');
        $seconds = (int) $flights->sum('duration');

        return [
            'flights' => $flights->count(),
            'miles' => $miles,
            'hours' => round($seconds / 3600, 1),
            'days' => round($seconds / 86400, 1),
            'airports' => $codes->count(),
            'countries' => $countries->count(),
            'airlines' => $flights->pluck('airline_icao')->filter()->unique()->count(),
            'aircraft' => $flights->map(fn (Flight $flight): mixed => data_get($flight->meta, 'aircraft'))->filter()->unique()->count(),
            'international' => $flights->count() - $domestic,
            'domestic' => $domestic,
            // A relatable yardstick: the Earth is 24,901 miles around.
            'aroundEarth' => round($miles / 24901, 2),
            'fromYear' => $first->occurred_at->year,
            'toYear' => $last->occurred_at->year,
            'fromLabel' => $first->occurred_at->format('F Y'),
            'toLabel' => $last->occurred_at->format('F Y'),
            'firstRoute' => "{$first->origin_iata} to {$first->destination_iata}",
            'updated' => $last->occurred_at->format('j F Y'),
        ];
    }

    /**
     * Flights and miles per calendar year, the lumpy rhythm of how I travel.
     *
     * @return Collection<int, array{year: int, flights: int, miles: int}>
     */
    private function byYear(Collection $flights): Collection
    {
        $byYear = $flights
            ->groupBy(fn (Flight $flight): int => $flight->occurred_at->year)
            ->map(fn (Collection $year, int $key): array => [
                'year' => $key,
                'flights' => $year->count(),
                'miles' => (int) $year->sum('distance_miles'),
            ]);

        // Fill the empty years between the first and last so the gaps (covid 2021,
        // the missing-record years) read as gaps rather than vanishing entirely.
        $from = (int) $flights->first()->occurred_at->year;
        $to = (int) $flights->last()->occurred_at->year;

        return collect(range($from, $to))
            ->map(fn (int $year): array => $byYear->get($year, ['year' => $year, 'flights' => 0, 'miles' => 0]))
            ->values();
    }

    /**
     * The longest and shortest legs, which happen to be from the same US trip.
     *
     * @return array{longest: array<string, mixed>, shortest: array<string, mixed>}
     */
    private function extremes(Collection $flights): array
    {
        $longest = $flights->sortByDesc('distance_miles')->first();
        $shortest = $flights->sortBy('distance_miles')->first();

        return [
            'longest' => $this->leg($longest),
            'shortest' => $this->leg($shortest),
        ];
    }

    /**
     * @return array{route: string, miles: int, when: string, date: string}
     */
    private function leg(Flight $flight): array
    {
        return [
            'origin' => $flight->origin_iata,
            'destination' => $flight->destination_iata,
            'route' => "{$flight->origin_iata} to {$flight->destination_iata}",
            'miles' => (int) $flight->distance_miles,
            'when' => $flight->occurred_at->format('F Y'),
            'date' => $flight->occurred_at->format('Y-m-d'),
        ];
    }

    /**
     * The home hub: how many of all flights touch it, and its lead over the next.
     *
     * @return array<string, mixed>
     */
    private function hub(Collection $flights): array
    {
        $counts = $this->airportCounts($flights);
        $hub = $counts[self::HUB] ?? 0;
        $next = collect($counts)->forget(self::HUB)->max() ?? 0;

        return [
            'code' => self::HUB,
            'flights' => $hub,
            'total' => $flights->count(),
            'pct' => $flights->count() > 0 ? (int) round($hub / $flights->count() * 100) : 0,
            'next' => $next,
        ];
    }

    /**
     * The most-used airports, for the per-airport bar chart.
     *
     * @return array<int, array{code: string, flights: int}>
     */
    private function topAirports(Collection $flights): array
    {
        return collect($this->airportCounts($flights))
            ->sortDesc()
            ->take(6)
            ->map(fn (int $count, string $code): array => ['code' => $code, 'flights' => $count])
            ->values()
            ->all();
    }

    /**
     * Count every appearance of an airport at either end of a flight.
     *
     * @return array<string, int>
     */
    private function airportCounts(Collection $flights): array
    {
        $counts = [];

        foreach ($flights as $flight) {
            foreach ([$flight->origin_iata, $flight->destination_iata] as $code) {
                if ($code) {
                    $counts[$code] = ($counts[$code] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }

    /**
     * The aircraft I've flown on, split into the everyday workhorses and the
     * rare one-offs (the single A380, the two Jamaica 747s).
     *
     * @return array<string, mixed>
     */
    private function fleet(Collection $flights): array
    {
        $counts = $flights
            ->map(fn (Flight $flight): mixed => data_get($flight->meta, 'aircraft'))
            ->filter()
            ->countBy()
            ->sortDesc();

        $top = $counts->take(5)
            ->map(fn (int $count, string $name): array => ['name' => $name, 'count' => $count])
            ->values()
            ->all();

        $onceOnly = $counts->filter(fn (int $count): bool => $count === 1)->count();

        $a380 = $flights->first(fn (Flight $flight): bool => data_get($flight->meta, 'aircraft') === 'Airbus A380');
        $jumbo = $flights->first(fn (Flight $flight): bool => data_get($flight->meta, 'aircraft') === 'Boeing 747-400');

        return [
            'types' => $counts->count(),
            'top' => $top,
            'onceOnly' => $onceOnly,
            'a380' => $a380 ? $this->leg($a380) : null,
            'jumbo' => $jumbo ? $this->leg($jumbo) : null,
        ];
    }

    /**
     * The window/aisle/middle split, for the window-seat-person angle. Only some
     * flights record a seat type, so this is the share of the ones that do.
     *
     * @return array<string, mixed>
     */
    private function seats(Collection $flights): array
    {
        $types = $flights->map(fn (Flight $flight): mixed => data_get($flight->meta, 'seat_type'))->filter();
        $known = $types->count();
        $counts = $types->countBy();

        $share = fn (string $type): int => $known > 0 ? (int) round(($counts[$type] ?? 0) / $known * 100) : 0;

        return [
            'known' => $known,
            'window' => (int) ($counts['WINDOW'] ?? 0),
            'aisle' => (int) ($counts['AISLE'] ?? 0),
            'middle' => (int) ($counts['MIDDLE'] ?? 0),
            'windowPct' => $share('WINDOW'),
            'aislePct' => $share('AISLE'),
            'middlePct' => $share('MIDDLE'),
        ];
    }

    /**
     * The budget-carrier share, the cheap way I get around Europe.
     *
     * @return array<string, mixed>
     */
    private function budget(Collection $flights): array
    {
        $easyjet = $flights->where('airline_icao', 'EZY')->count();
        $budget = $flights->whereIn('airline_icao', self::BUDGET_ICAO)->count();
        $total = $flights->count();

        $easyjetAirline = Airline::query()->where('icao_code', 'EZY')->first();

        return [
            'easyjet' => $easyjet,
            'easyjetPct' => $total > 0 ? (int) round($easyjet / $total * 100) : 0,
            'easyjetIcon' => $easyjetAirline?->icon_url,
            'easyjetHref' => $easyjetAirline ? '/flights/'.Str::slug($easyjetAirline->name) : null,
            'budget' => $budget,
            'budgetPct' => $total > 0 ? (int) round($budget / $total * 100) : 0,
            'total' => $total,
        ];
    }

    /**
     * The business/personal split. The 11 business legs are the WordCamp Europe
     * trips, one new city in the first week of June each year.
     *
     * @return array<string, mixed>
     */
    private function purpose(Collection $flights): array
    {
        $business = $flights->where('reason', 'business')->count();
        $personal = $flights->where('reason', 'personal')->count();

        return [
            'business' => $business,
            'personal' => $personal,
            'businessPct' => $flights->count() > 0 ? (int) round($business / $flights->count() * 100) : 0,
        ];
    }

    /**
     * The longest stretches with no flights at all: the covid void of 2021 and
     * the wide gaps in the early, sparsely-recorded years.
     *
     * @return array<int, array{days: int, years: int, from: string, to: string}>
     */
    private function gaps(Collection $flights): array
    {
        $gaps = [];
        $previous = null;

        foreach ($flights as $flight) {
            if ($previous) {
                $days = (int) $previous->occurred_at->diffInDays($flight->occurred_at);
                $gaps[] = [
                    'days' => $days,
                    'years' => (int) round($days / 365),
                    'from' => $previous->occurred_at->format('F Y'),
                    'to' => $flight->occurred_at->format('F Y'),
                ];
            }

            $previous = $flight;
        }

        usort($gaps, fn (array $a, array $b): int => $b['days'] <=> $a['days']);

        return array_slice($gaps, 0, 3);
    }
}
