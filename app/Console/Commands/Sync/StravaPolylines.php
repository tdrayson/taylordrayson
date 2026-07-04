<?php

namespace App\Console\Commands\Sync;

use App\Models\Activity;
use App\Services\Strava;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('strava:polylines {--limit=0 : Max activities to fetch (0 = all)} {--force : Re-fetch even if polyline exists}')]
#[Description('Fetch polylines from Strava API for run/walk/ride activities')]
class StravaPolylines extends Command
{
    private const RATE_LIMIT = 95;

    private const RATE_WINDOW = 900;

    private const POLYLINE_TYPES = ['run', 'walk', 'ride', 'e-bike-ride'];

    public function handle(Strava $strava): int
    {
        if (! $strava->token()) {
            $this->error('Could not obtain a Strava access token.');

            return self::FAILURE;
        }

        $query = Activity::query()
            ->where('source', 'strava')
            ->whereNotNull('source_id')
            ->whereIn('type', self::POLYLINE_TYPES);

        if (! $this->option('force')) {
            $query->whereNull('meta->polyline');
        }

        $activities = $query->orderBy('occurred_at')->get();

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $activities = $activities->take($limit);
        }

        $this->info("Found {$activities->count()} activities to fetch polylines for.");

        if ($activities->isEmpty()) {
            return self::SUCCESS;
        }

        $csvPath = database_path('../data/activities.csv');
        $csvData = $this->loadCsv($csvPath);

        $fetched = 0;
        $requestsInWindow = 0;
        $windowStart = time();

        foreach ($activities as $activity) {
            if ($requestsInWindow >= self::RATE_LIMIT) {
                $elapsed = time() - $windowStart;
                $wait = self::RATE_WINDOW - $elapsed;
                if ($wait > 0) {
                    $this->writeCsv($csvPath, $csvData);
                    $this->info("Rate limit reached. CSV saved. Waiting {$wait}s...");
                    sleep($wait);
                }
                $requestsInWindow = 0;
                $windowStart = time();
            }

            $data = $strava->activity($activity->source_id);

            $requestsInWindow++;

            if ($data === null) {
                $this->warn("Failed to fetch {$activity->source_id}");

                continue;
            }

            $polyline = $data['map']['polyline'] ?? null;

            if ($polyline) {
                $meta = $activity->meta ?? [];
                $meta['polyline'] = $polyline;
                $activity->update(['meta' => $meta]);

                $this->updateCsvRow($csvData, $activity->source_id, $meta);
                $fetched++;
            }

            $this->info("[{$fetched}/{$activities->count()}] {$activity->name} — ".($polyline ? 'polyline saved' : 'no polyline'));
        }

        $this->writeCsv($csvPath, $csvData);
        $this->info("Done. Fetched polylines for {$fetched} activities. CSV updated.");

        return self::SUCCESS;
    }

    /**
     * @return array{headers: string[], rows: array<int, array<int, string>>}
     */
    private function loadCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  array{headers: string[], rows: array<int, array<int, string>>}  $csvData
     */
    private function updateCsvRow(array &$csvData, string $sourceId, array $meta): void
    {
        $sourceIdIndex = array_search('source_id', $csvData['headers']);
        $metaIndex = array_search('meta', $csvData['headers']);

        if ($sourceIdIndex === false || $metaIndex === false) {
            return;
        }

        foreach ($csvData['rows'] as &$row) {
            if (($row[$sourceIdIndex] ?? null) === $sourceId) {
                $row[$metaIndex] = json_encode($meta);
                break;
            }
        }
    }

    /**
     * @param  array{headers: string[], rows: array<int, array<int, string>>}  $csvData
     */
    private function writeCsv(string $path, array $csvData): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, $csvData['headers']);
        foreach ($csvData['rows'] as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }
}
