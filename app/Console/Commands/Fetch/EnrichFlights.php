<?php

namespace App\Console\Commands\Fetch;

use App\Models\Airport;
use App\Models\Flight;
use App\Services\LogoStream;
use App\Support\Distance;
use App\Support\VenueTimezone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('flights:enrich {--force : Re-fetch route info for flights that already have it}')]
#[Description('Add flight duration and departure/arrival timezones, sourced from the aviation API with a coordinate/timezone fallback')]
class EnrichFlights extends Command
{
    /** @var list<string> */
    /** @var array<string, array{lat: float, lng: float}> */
    private array $airports = [];

    /** @var array<string, array<string, int|string|null>> */
    private array $routeCache = [];

    public function __construct(
        private readonly LogoStream $logoStream,
        private readonly VenueTimezone $timezones,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('services.logostream.key')) {
            $this->components->error('LOGOSTREAM_KEY is not set.');

            return self::FAILURE;
        }

        $this->loadAirports();

        $flights = Flight::query()->orderBy('occurred_at')->get();
        $updated = 0;

        foreach ($flights as $flight) {
            $info = $this->infoFor($flight);

            $flight->update($info);
            $updated++;
        }

        $this->newLine();
        $this->components->info("Enriched {$updated} flights.");

        return self::SUCCESS;
    }

    /**
     * Duration and timezones for one flight, left as stored unless --force is
     * given: the aviation API is rate-limited and most rows never change.
     *
     * @return array<string, mixed>
     */
    private function infoFor(Flight $flight): array
    {
        if (! $this->option('force') && $flight->departure_timezone) {
            return [
                'duration' => $flight->duration,
                'departure_timezone' => $flight->departure_timezone,
                'arrival_timezone' => $flight->arrival_timezone,
            ];
        }

        return $this->routeInfo(
            $flight->origin_iata,
            $flight->destination_iata,
            Distance::miles($flight->distance) ?? 0,
        );
    }

    private function loadAirports(): void
    {
        $this->airports = Airport::query()
            ->whereNotNull('latitude')
            ->get(['iata_code', 'latitude', 'longitude'])
            ->keyBy('iata_code')
            ->map(fn (Airport $airport): array => ['lat' => (float) $airport->latitude, 'lng' => (float) $airport->longitude])
            ->all();
    }

    /**
     * @return array<string, int|string|null>
     */
    private function routeInfo(string $departure, string $arrival, int $miles): array
    {
        $key = "{$departure}-{$arrival}";

        if (isset($this->routeCache[$key])) {
            return $this->routeCache[$key];
        }

        $info = $this->fromAviationApi($departure, $arrival);

        if ($info === null) {
            $distance = $this->distanceMiles($departure, $arrival) ?? ($miles ?: null);

            $info = [
                'duration' => $this->estimateDuration((int) ($distance ?? 0)),
                'departure_timezone' => $this->timezoneFor($departure),
                'arrival_timezone' => $this->timezoneFor($arrival),
                'distance' => $distance !== null ? Distance::fromMiles($distance) : null,
            ];
            $this->components->warn("{$departure} → {$arrival} not in aviation API - used coordinate/timezone fallback");
        } else {
            $this->components->task("{$departure} → {$arrival}");
        }

        return $this->routeCache[$key] = $info;
    }

    /**
     * @return array<string, int|string|null>|null
     */
    private function fromAviationApi(string $departure, string $arrival): ?array
    {
        return $this->logoStream->route($departure, $arrival);
    }

    private function timezoneFor(string $iata): ?string
    {
        $airport = $this->airports[$iata] ?? null;

        if (! $airport) {
            return null;
        }

        return $this->timezones->forCoordinate($airport['lat'], $airport['lng']);
    }

    private function estimateDuration(int $miles): ?int
    {
        if ($miles <= 0) {
            return null;
        }

        // ~500 mph cruise + 25 min taxi, returned in seconds.
        return (int) round((($miles / 500) * 60 + 25) * 60);
    }

    /**
     * Great-circle distance in miles between two airports, from their stored
     * coordinates (the fallback when the aviation API has no route).
     */
    private function distanceMiles(string $departure, string $arrival): ?int
    {
        $from = $this->airports[$departure] ?? null;
        $to = $this->airports[$arrival] ?? null;

        if ($from === null || $to === null) {
            return null;
        }

        $earthRadiusMiles = 3958.8;
        $deltaLat = deg2rad($to['lat'] - $from['lat']);
        $deltaLng = deg2rad($to['lng'] - $from['lng']);

        $haversine = sin($deltaLat / 2) ** 2
            + cos(deg2rad($from['lat'])) * cos(deg2rad($to['lat'])) * sin($deltaLng / 2) ** 2;

        return (int) round($earthRadiusMiles * 2 * asin(min(1.0, sqrt($haversine))));
    }
}
