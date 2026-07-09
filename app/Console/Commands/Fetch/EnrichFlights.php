<?php

namespace App\Console\Commands\Fetch;

use App\Models\Airport;
use App\Models\Flight;
use App\Services\LogoStream;
use App\Services\TimeApi;
use App\Support\Distance;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('flights:enrich {--force : Re-fetch route info for rows that already have it} {--file= : CSV path to enrich (defaults to data/flights.csv)}')]
#[Description('Add flight duration and departure/arrival timezones to data/flights.csv and the database, sourced from the aviation API with a coordinate/timezone fallback')]
class EnrichFlights extends Command
{
    /** @var list<string> */
    private const COLUMNS = ['duration', 'departure_timezone', 'arrival_timezone'];

    /** @var array<string, array{lat: float, lng: float}> */
    private array $airports = [];

    /** @var array<string, array<string, int|string|null>> */
    private array $routeCache = [];

    public function __construct(
        private readonly LogoStream $logoStream,
        private readonly TimeApi $timeApi,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = $this->option('file') ?: base_path('data/flights.csv');

        if (! file_exists($path)) {
            $this->components->error("CSV not found: {$path}");

            return self::FAILURE;
        }

        if (! config('services.logostream.key')) {
            $this->components->error('LOGOSTREAM_KEY is not set.');

            return self::FAILURE;
        }

        $this->loadAirports();

        [$headers, $rows] = $this->readCsv($path);
        $outHeaders = array_values(array_unique([...$headers, ...self::COLUMNS]));

        $output = [];
        $updated = 0;

        foreach ($rows as $row) {
            $info = $this->infoForRow($row);
            $row = [...$row, ...$info];

            $flight = Flight::query()
                ->where('flight_number', $row['flight_number'])
                ->where('occurred_at', Carbon::parse($row['occurred_at']))
                ->first();

            if ($flight) {
                $flight->update($info);
                $updated++;
            }

            $output[] = array_map(fn (string $header): string => $this->csvValue($row[$header] ?? null), $outHeaders);
        }

        $this->writeCsv($path, $outHeaders, $output);

        $this->newLine();
        $this->components->info("Enriched data/flights.csv and updated {$updated} flights in the database.");

        return self::SUCCESS;
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
     * @return array{0: list<string>, 1: list<array<string, string>>}
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }

            $rows[] = array_combine($headers, $row);
        }

        fclose($handle);

        return [$headers, $rows];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function writeCsv(string $path, array $headers, array $rows): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, int|string|null>
     */
    private function infoForRow(array $row): array
    {
        if (! $this->option('force') && ($row['departure_timezone'] ?? '') !== '') {
            return [
                'duration' => ($row['duration'] ?? '') !== '' ? (int) $row['duration'] : null,
                'departure_timezone' => $row['departure_timezone'],
                'arrival_timezone' => ($row['arrival_timezone'] ?? '') ?: null,
            ];
        }

        $miles = ($row['distance'] ?? '') !== '' ? Distance::miles((int) $row['distance']) : null;

        return $this->routeInfo($row['origin_iata'], $row['destination_iata'], $miles ?? 0);
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

        return $this->timeApi->timezoneForCoordinate($airport['lat'], $airport['lng']);
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

    private function csvValue(int|string|null $value): string
    {
        return $value === null ? '' : (string) $value;
    }
}
