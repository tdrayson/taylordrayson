<?php

namespace App\Console\Commands\Sync;

use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

#[Signature('strava:backfill-timezones {--per-page=200 : Activities per page}')]
#[Description('Backfill timezone and local occurred_at on existing Strava activities from the list endpoint')]
class BackfillStravaTimezones extends Command
{
    /**
     * Execute the console command.
     *
     * Pages all Strava summary activities, finds matching local Activity rows by
     * platform_id, and updates occurred_at (to local wall-clock) and timezone
     * (IANA name). Does not write the CSV seed file.
     *
     * @return int Command exit code
     */
    public function handle(): int
    {
        $token = $this->resolveAccessToken();

        if (! $token) {
            $this->error('Could not obtain a Strava access token.');

            return self::FAILURE;
        }

        $perPage = (int) $this->option('per-page');
        $page = 1;
        $updated = 0;

        do {
            $response = Http::withToken($token)->get('https://www.strava.com/api/v3/athlete/activities', [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if ($response->failed()) {
                $this->error("Strava request failed on page {$page}: {$response->status()}");

                return self::FAILURE;
            }

            $batch = $response->json();

            foreach ($batch as $summary) {
                $activity = Activity::query()
                    ->where('platform_type', 'strava')
                    ->where('platform_id', (string) $summary['id'])
                    ->first();

                if ($activity === null) {
                    continue;
                }

                $localDate = $summary['start_date_local'] ?? $summary['start_date'];
                $ianaTimezone = Str::afterLast((string) ($summary['timezone'] ?? ''), ' ') ?: null;

                // Strava's start_date_local carries a Z; parse as UTC so the wall-clock digits are kept verbatim.
                $activity->update([
                    'occurred_at' => Carbon::parse($localDate, 'UTC')->format('Y-m-d H:i:s'),
                    'timezone' => $ianaTimezone,
                ]);

                $updated++;
            }

            $this->info("Page {$page}: ".count($batch).' activities scanned.');
            $page++;
        } while (count($batch) === $perPage);

        $this->info("Backfilled {$updated} activities.");

        return self::SUCCESS;
    }

    /**
     * Resolve a valid Strava access token, refreshing via OAuth if needed.
     *
     * @return string|null The access token, or null on failure
     */
    private function resolveAccessToken(): ?string
    {
        $cached = cache('strava_access_token');

        if ($cached) {
            return $cached;
        }

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

        return $data['access_token'];
    }
}
