<?php

namespace App\Console\Commands\Import;

use App\Actions\Fuel\ExtractReceiptLocation;
use App\Models\Fuel;
use App\Services\PetrolPrices;
use App\Services\PetrolPrices\FuelStationResult;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('import:fuel-receipts {folder : Folder of GPS-tagged receipt photos} {--apply : Write the reviewed CSV to the database} {--window=1440 : Max match window in minutes} {--review= : Review CSV path (default storage/app/fuel/receipt-review.csv)}')]
#[Description('Backfill fuel logs with garage/location data from receipt photos')]
class ImportFuelReceipts extends Command
{
    private const REVIEW_HEADER = [
        'receipt_file', 'receipt_time', 'fuel_id', 'fuel_occurred_at', 'delta_minutes',
        'receipt_lat', 'receipt_lng', 'station_name', 'brand', 'address', 'postcode',
        'city', 'station_lat', 'station_lng', 'distance_km', 'alt1_name', 'alt2_name', 'flag',
    ];

    public function handle(PetrolPrices $petrolPrices, ExtractReceiptLocation $extract): int
    {
        $reviewPath = $this->option('review') ?: storage_path('app/fuel/receipt-review.csv');

        return $this->option('apply')
            ? $this->apply($reviewPath)
            : $this->dryRun($petrolPrices, $extract, $reviewPath);
    }

    private function dryRun(PetrolPrices $petrolPrices, ExtractReceiptLocation $extract, string $reviewPath): int
    {
        $folder = $this->argument('folder');

        if (! File::isDirectory($folder)) {
            $this->error("Folder not found: {$folder}");

            return self::FAILURE;
        }

        $window = (int) $this->option('window');
        $entries = Fuel::query()->get(['id', 'occurred_at']);

        /** @var array<string, array<int, FuelStationResult>> $stationCache */
        $stationCache = [];
        $rows = [];
        $matched = 0;
        $skipped = 0;

        foreach ($this->imageFiles($folder) as $path) {
            $location = $extract($path);

            if ($location === null) {
                $skipped++;
                $this->warn('Skipped (no GPS/time): '.basename($path));

                continue;
            }

            $best = null;
            $bestDelta = null;
            foreach ($entries as $entry) {
                $delta = (int) abs($entry->occurred_at->diffInMinutes($location->capturedAt));
                if ($delta <= $window && ($bestDelta === null || $delta < $bestDelta)) {
                    $best = $entry;
                    $bestDelta = $delta;
                }
            }

            $cacheKey = round($location->latitude, 4).','.round($location->longitude, 4);
            $stations = $stationCache[$cacheKey] ??= $petrolPrices->search(
                latitude: $location->latitude,
                longitude: $location->longitude,
                radiusKm: 5,
            );
            usort($stations, fn (FuelStationResult $a, FuelStationResult $b): int => ($a->distanceKm ?? INF) <=> ($b->distanceKm ?? INF));

            $station = $stations[0] ?? null;
            $flag = match (true) {
                $best === null => 'unmatched',
                $station === null => 'no-station',
                $bestDelta > 60 => 'check-delta',
                default => 'ok',
            };

            if ($best !== null && $station !== null) {
                $matched++;
            }

            $rows[] = [
                basename($path),
                $location->capturedAt->format('Y-m-d H:i:s'),
                $best?->id ?? '',
                $best?->occurred_at?->format('Y-m-d H:i:s') ?? '',
                $bestDelta ?? '',
                $location->latitude,
                $location->longitude,
                $station?->stationName ?? '',
                $station?->brand ?? '',
                $station?->address ?? '',
                $station?->postcode ?? '',
                $station?->city ?? '',
                $station?->latitude ?? '',
                $station?->longitude ?? '',
                $station?->distanceKm ?? '',
                $stations[1]->stationName ?? '',
                $stations[2]->stationName ?? '',
                $flag,
            ];
        }

        $this->writeCsv($reviewPath, $rows);

        $this->info("Wrote {$reviewPath}: {$matched} matched, {$skipped} skipped, ".count($rows).' rows total.');
        $this->line('Review and edit the CSV, then re-run with --apply.');

        return self::SUCCESS;
    }

    private function apply(string $reviewPath): int
    {
        if (! File::exists($reviewPath)) {
            $this->error("Review file not found: {$reviewPath}");

            return self::FAILURE;
        }

        $handle = fopen($reviewPath, 'r');
        $header = fgetcsv($handle);
        $updated = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }
            $data = array_combine($header, $row);

            if ($data['fuel_id'] === '' || $data['station_name'] === '') {
                continue;
            }

            $fuel = Fuel::query()->find($data['fuel_id']);
            if ($fuel === null) {
                continue;
            }

            $fuel->update([
                'station_name' => $data['station_name'],
                'brand' => $data['brand'] ?: null,
                'address' => $data['address'] ?: null,
                'postcode' => $data['postcode'] ?: null,
                'city' => $data['city'] ?: null,
                'country' => 'United Kingdom',
                'latitude' => ($data['station_lat'] ?: $data['receipt_lat']) ?: null,
                'longitude' => ($data['station_lng'] ?: $data['receipt_lng']) ?: null,
            ]);
            $updated++;
        }

        fclose($handle);

        $this->call('fuel:brand-logos');
        $this->info("Applied {$updated} rows.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function imageFiles(string $folder): array
    {
        return collect(File::files($folder))
            ->filter(fn ($file): bool => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'heic'], true))
            ->map(fn ($file): string => $file->getPathname())
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'w');
        fputcsv($handle, self::REVIEW_HEADER, ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }
        fclose($handle);
    }
}
