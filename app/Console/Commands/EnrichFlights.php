<?php

namespace App\Console\Commands;

use App\Models\Airport;
use App\Models\Flight;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

#[Signature('flights:enrich {--force : Re-fetch route info for rows that already have it} {--file= : CSV path to enrich (defaults to data/flights.csv)}')]
#[Description('Add flight duration, departure/arrival timezones and CO2 to data/flights.csv and the database, sourced from the aviation API with a timezone fallback')]
class EnrichFlights extends Command
{
    /** @var list<string> */
    private const COLUMNS = ['duration_min', 'departure_timezone', 'arrival_timezone', 'co2_kg'];

    /** @var array<string, array{lat: float, lng: float}> */
    private array $airports = [];

    /** @var array<string, array<string, int|string|null>> */
    private array $routeCache = [];

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
                'duration_min' => ($row['duration_min'] ?? '') !== '' ? (int) $row['duration_min'] : null,
                'departure_timezone' => $row['departure_timezone'],
                'arrival_timezone' => ($row['arrival_timezone'] ?? '') ?: null,
                'co2_kg' => ($row['co2_kg'] ?? '') !== '' ? (int) $row['co2_kg'] : null,
            ];
        }

        return $this->routeInfo($row['origin_iata'], $row['destination_iata'], (int) ($row['distance_miles'] ?: 0));
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
            $info = [
                'duration_min' => $this->estimateDuration($miles),
                'departure_timezone' => $this->timezoneFor($departure),
                'arrival_timezone' => $this->timezoneFor($arrival),
                'co2_kg' => null,
            ];
            $this->components->warn("{$departure} → {$arrival} not in aviation API — used timezone fallback");
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
        $response = Http::withHeaders(['x-api-key' => config('services.logostream.key')])
            ->get(rtrim((string) config('services.logostream.aviation_url'), '/').'/v1/routes', [
                'departureIata' => $departure,
                'arrivalIata' => $arrival,
                'limit' => 1,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $route = $response->json('data.0');

        if (! $route) {
            return null;
        }

        return [
            'duration_min' => $route['duration_min'] ?? null,
            'departure_timezone' => $route['departure_timezone'] ?? null,
            'arrival_timezone' => $route['arrival_timezone'] ?? null,
            'co2_kg' => $route['co2_kg'] ?? null,
        ];
    }

    private function timezoneFor(string $iata): ?string
    {
        $airport = $this->airports[$iata] ?? null;

        if (! $airport) {
            return null;
        }

        $response = Http::get(rtrim((string) config('services.timeapi.url'), '/').'/api/timezone/coordinate', [
            'latitude' => $airport['lat'],
            'longitude' => $airport['lng'],
        ]);

        return $response->successful() ? $response->json('timeZone') : null;
    }

    private function estimateDuration(int $miles): ?int
    {
        if ($miles <= 0) {
            return null;
        }

        return (int) round(($miles / 500) * 60 + 25);
    }

    private function csvValue(int|string|null $value): string
    {
        return $value === null ? '' : (string) $value;
    }
}
