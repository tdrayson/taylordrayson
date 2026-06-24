<?php

namespace App\Console\Commands;

use App\Actions\GenerateStaticMap;
use App\Models\Activity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

#[Signature('strava:sync {--days=7 : How many days back to check for new activities}')]
#[Description('Sync new Strava activities to the database')]
class StravaSync extends Command
{
    /** @var array<string, string> */
    private const TYPE_MAP = [
        'Run' => 'run',
        'TrailRun' => 'run',
        'VirtualRun' => 'run',
        'Walk' => 'walk',
        'Hike' => 'walk',
        'Ride' => 'ride',
        'VirtualRide' => 'ride',
        'GravelRide' => 'ride',
        'MountainBikeRide' => 'ride',
        'EBikeRide' => 'e-bike ride',
        'EMountainBikeRide' => 'e-bike ride',
        'Swim' => 'swim',
        'Workout' => 'workout',
        'WeightTraining' => 'weight training',
        'Yoga' => 'yoga',
        'IceSkate' => 'ice skate',
        'Squash' => 'workout',
        'Tennis' => 'workout',
        'Badminton' => 'workout',
        'Racquetball' => 'workout',
        'Pickleball' => 'workout',
        'Soccer' => 'workout',
        'Crossfit' => 'workout',
        'HighIntensityIntervalTraining' => 'workout',
        'Elliptical' => 'workout',
        'StairStepper' => 'workout',
        'Rowing' => 'workout',
        'Pilates' => 'workout',
    ];

    public function handle(): int
    {
        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            return self::FAILURE;
        }

        $after = now()->subDays((int) $this->option('days'))->timestamp;

        $stravaActivities = $this->fetchActivities($accessToken, $after);

        if ($stravaActivities === null) {
            return self::FAILURE;
        }

        $existingIds = Activity::query()
            ->where('platform_type', 'strava')
            ->whereNotNull('platform_id')
            ->pluck('platform_id')
            ->all();

        $newActivities = collect($stravaActivities)
            ->reject(fn (array $a) => in_array((string) $a['id'], $existingIds));

        $this->info("Found {$newActivities->count()} new activities to sync.");

        if ($newActivities->isEmpty()) {
            return self::SUCCESS;
        }

        $synced = 0;

        foreach ($newActivities as $stravaActivity) {
            $detail = $this->fetchDetail($accessToken, $stravaActivity['id']);
            if (! $detail) {
                continue;
            }

            $activity = $this->createActivity($detail);
            $this->downloadPhotos($accessToken, $detail, $activity);
            app(GenerateStaticMap::class)($activity);

            $synced++;
            $this->info("[{$synced}] {$activity->name}");
        }

        $this->info("Done. Synced {$synced} activities.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchActivities(string $token, int $after): ?array
    {
        $activities = [];
        $page = 1;

        while (true) {
            $response = Http::withToken($token)
                ->get('https://www.strava.com/api/v3/athlete/activities', [
                    'after' => $after,
                    'per_page' => 200,
                    'page' => $page,
                ]);

            if ($response->failed()) {
                $this->error("Failed to fetch activities: {$response->status()} — {$response->body()}");

                return null;
            }

            $batch = $response->json();
            if (empty($batch)) {
                break;
            }

            $activities = array_merge($activities, $batch);
            $page++;
        }

        return $activities;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchDetail(string &$token, int $activityId): ?array
    {
        $response = Http::withToken($token)
            ->get("https://www.strava.com/api/v3/activities/{$activityId}");

        if ($response->status() === 401) {
            $this->warn('Token expired, refreshing...');
            $token = $this->refreshAccessToken();
            if (! $token) {
                return null;
            }

            $response = Http::withToken($token)
                ->get("https://www.strava.com/api/v3/activities/{$activityId}");
        }

        if ($response->failed()) {
            $this->warn("Failed to fetch activity {$activityId}: {$response->status()}");

            return null;
        }

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createActivity(array $data): Activity
    {
        $sportType = $data['sport_type'] ?? $data['type'] ?? 'Workout';
        $type = self::TYPE_MAP[$sportType] ?? strtolower(
            Str::snake($sportType, ' ')
        );

        $meta = array_filter([
            'elapsed_time' => $data['elapsed_time'] ?? null,
            'total_elevation_gain' => $data['total_elevation_gain'] ?? null,
            'elev_high' => $data['elev_high'] ?? null,
            'elev_low' => $data['elev_low'] ?? null,
            'max_speed' => $data['max_speed'] ?? null,
            'average_speed' => $data['average_speed'] ?? null,
            'average_cadence' => $data['average_cadence'] ?? null,
            'sport_type' => $sportType,
        ], fn ($v) => $v !== null && $v !== 0 && $v !== 0.0);

        $polyline = $data['map']['polyline'] ?? null;
        if ($polyline) {
            $meta['polyline'] = $polyline;
        }

        return Activity::create([
            'occurred_at' => $data['start_date'],
            'type' => $type,
            'name' => $data['name'],
            'duration' => $data['moving_time'],
            'calories' => $data['calories'] ?: null,
            'distance_km' => $data['distance'] ? round($data['distance'] / 1000, 3) : null,
            'average_heart_rate' => $data['average_heartrate'] ?? null,
            'max_heart_rate' => $data['max_heartrate'] ?? null,
            'platform_type' => 'strava',
            'platform_id' => (string) $data['id'],
            'meta' => $meta ?: null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function downloadPhotos(string $token, array $data, Activity $activity): void
    {
        $photoCount = $data['total_photo_count'] ?? 0;
        if ($photoCount === 0) {
            return;
        }

        $response = Http::withToken($token)
            ->get("https://www.strava.com/api/v3/activities/{$data['id']}/photos", [
                'size' => 2048,
            ]);

        if ($response->failed()) {
            $this->warn("Failed to fetch photos for {$data['id']}");

            return;
        }

        $photos = $response->json();

        foreach ($photos as $index => $photo) {
            $url = $photo['urls']['2048'] ?? $photo['urls']['600'] ?? null;
            if (! $url) {
                continue;
            }

            $imageResponse = Http::get($url);
            if ($imageResponse->failed()) {
                continue;
            }

            $activity->addMediaFromString($imageResponse->body())
                ->usingFileName(($photo['unique_id'] ?? Str::uuid()).'.jpg')
                ->toMediaCollection($index === 0 ? 'cover' : 'photos');
        }

        $this->info("  → Downloaded {$photoCount} photo(s)");
    }

    private function getAccessToken(): ?string
    {
        return cache('strava_access_token')
            ?? $this->refreshAccessToken();
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
