<?php

namespace App\Console\Commands\Sync;

use App\Models\Activity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('strava:polylines {--limit=0 : Max activities to fetch (0 = all)} {--force : Re-fetch even if polyline exists}')]
#[Description('Fetch polylines from Strava API for run/walk/ride activities')]
class StravaPolylines extends Command
{
    private const RATE_LIMIT = 95;

    private const RATE_WINDOW = 900;

    private const POLYLINE_TYPES = ['run', 'walk', 'ride', 'e-bike-ride'];

    public function handle(): int
    {
        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            return self::FAILURE;
        }

        $query = Activity::query()
            ->where('platform_type', 'strava')
            ->whereNotNull('platform_id')
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

            $response = Http::withToken($accessToken)
                ->get("https://www.strava.com/api/v3/activities/{$activity->platform_id}");

            $requestsInWindow++;

            if ($response->status() === 401) {
                $this->warn('Token expired, refreshing...');
                $accessToken = $this->refreshAccessToken();
                if (! $accessToken) {
                    $this->writeCsv($csvPath, $csvData);

                    return self::FAILURE;
                }

                $response = Http::withToken($accessToken)
                    ->get("https://www.strava.com/api/v3/activities/{$activity->platform_id}");
                $requestsInWindow++;
            }

            if ($response->failed()) {
                $this->warn("Failed to fetch {$activity->platform_id}: {$response->status()} — {$response->body()}");

                continue;
            }

            $data = $response->json();
            $polyline = $data['map']['polyline'] ?? null;

            if ($polyline) {
                $meta = $activity->meta ?? [];
                $meta['polyline'] = $polyline;
                $activity->update(['meta' => $meta]);

                $this->updateCsvRow($csvData, $activity->platform_id, $meta);
                $fetched++;
            }

            $this->info("[{$fetched}/{$activities->count()}] {$activity->name} — ".($polyline ? 'polyline saved' : 'no polyline'));
        }

        $this->writeCsv($csvPath, $csvData);
        $this->info("Done. Fetched polylines for {$fetched} activities. CSV updated.");

        return self::SUCCESS;
    }

    private function getAccessToken(): ?string
    {
        return cache('strava_access_token')
            ?? $this->refreshAccessToken();
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
    private function updateCsvRow(array &$csvData, string $platformId, array $meta): void
    {
        $platformIdIndex = array_search('platform_id', $csvData['headers']);
        $metaIndex = array_search('meta', $csvData['headers']);

        if ($platformIdIndex === false || $metaIndex === false) {
            return;
        }

        foreach ($csvData['rows'] as &$row) {
            if (($row[$platformIdIndex] ?? null) === $platformId) {
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

    private function refreshAccessToken(): ?string
    {
        $response = Http::post('https://www.strava.com/oauth/token', [
            'client_id' => config('services.strava.client_id'),
            'client_secret' => config('services.strava.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => config('services.strava.refresh_token'),
        ]);

        if ($response->failed()) {
            $this->error('Failed to refresh Strava access token: '.$response->body());

            return null;
        }

        $data = $response->json();
        $expiresIn = $data['expires_in'] ?? 3600;

        cache(['strava_access_token' => $data['access_token']], $expiresIn - 60);

        $this->info('Access token refreshed. Athlete: '.($data['athlete']['id'] ?? 'n/a'));

        return $data['access_token'];
    }
}
