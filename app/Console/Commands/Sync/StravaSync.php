<?php

namespace App\Console\Commands\Sync;

use App\Actions\GenerateStaticMap;
use App\Actions\StoreActivityStreams;
use App\Actions\SyncStravaPhotos;
use App\Models\Activity;
use App\Services\Strava;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
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
        'EBikeRide' => 'e-bike-ride',
        'EMountainBikeRide' => 'e-bike-ride',
        'Swim' => 'swim',
        'Workout' => 'workout',
        'WeightTraining' => 'weight-training',
        'Yoga' => 'yoga',
        'IceSkate' => 'ice-skate',
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

    public function handle(Strava $strava): int
    {
        if (! $strava->token()) {
            $this->error('Could not obtain a Strava access token.');

            return self::FAILURE;
        }

        $after = now()->subDays((int) $this->option('days'))->timestamp;

        $stravaActivities = $this->fetchActivities($strava, $after);

        if ($stravaActivities === null) {
            return self::FAILURE;
        }

        $existingIds = Activity::query()
            ->where('source', 'strava')
            ->whereNotNull('source_id')
            ->pluck('source_id')
            ->all();

        $newActivities = collect($stravaActivities)
            ->reject(fn (array $a) => in_array((string) $a['id'], $existingIds));

        $this->info("Found {$newActivities->count()} new activities to sync.");

        if ($newActivities->isEmpty()) {
            return self::SUCCESS;
        }

        $created = [];

        foreach ($newActivities as $stravaActivity) {
            $detail = $strava->activity($stravaActivity['id']);
            if (! $detail) {
                continue;
            }

            $activity = $this->createActivity($detail);
            $this->downloadPhotos($strava, $detail, $activity);
            app(GenerateStaticMap::class)($activity);
            app(StoreActivityStreams::class)($activity, $strava);

            $created[] = $activity;
            $this->info('['.count($created).'] '.$activity->name);
        }

        $appended = $this->appendActivitiesToCsv($created);

        if ($appended > 0) {
            $this->info("Appended {$appended} row(s) to data/activities.csv.");
        }

        $this->info('Done. Synced '.count($created).' activities.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchActivities(Strava $strava, int $after): ?array
    {
        $activities = [];
        $page = 1;

        while (true) {
            $batch = $strava->activitiesPage($page, 200, $after);

            if ($batch === null) {
                $this->error('Failed to fetch activities from Strava.');

                return null;
            }

            if ($batch === []) {
                break;
            }

            $activities = array_merge($activities, $batch);
            $page++;
        }

        return $activities;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createActivity(array $data): Activity
    {
        $sportType = $data['sport_type'] ?? $data['type'] ?? 'Workout';
        $type = self::TYPE_MAP[$sportType] ?? Str::kebab($sportType);

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

        $localDate = $data['start_date_local'] ?? $data['start_date'];

        return Activity::create([
            // Strava's start_date_local carries a Z; parse as UTC so the wall-clock digits are kept verbatim.
            'occurred_at' => Carbon::parse($localDate, 'UTC')->format('Y-m-d H:i:s'),
            'type' => $type,
            'name' => $data['name'],
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'duration' => $data['moving_time'],
            'calories' => $data['calories'] ?: null,
            'distance' => $data['distance'] ? (int) round($data['distance']) : null,
            'average_heart_rate' => $data['average_heartrate'] ?? null,
            'max_heart_rate' => $data['max_heartrate'] ?? null,
            'source' => 'strava',
            'source_id' => (string) $data['id'],
            'timezone' => $this->ianaTimezone($data['timezone'] ?? null),
            'meta' => $meta ?: null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function downloadPhotos(Strava $strava, array $data, Activity $activity): void
    {
        if (($data['total_photo_count'] ?? 0) === 0) {
            return;
        }

        $photos = $strava->activityPhotos($data['id']);

        if ($photos === null) {
            $this->warn("Failed to fetch photos for {$data['id']}");

            return;
        }

        $stored = app(SyncStravaPhotos::class)($activity, $photos);

        if ($stored > 0) {
            $this->info("  → Downloaded {$stored} photo(s)");
        }
    }

    /**
     * Append newly-synced activities to data/activities.csv (the seed used to
     * populate production via import:all), matching its header order and the
     * json_encode + fputcsv encoding the rest of the pipeline uses.
     *
     * @param  array<int, Activity>  $activities
     * @param  string|null  $path  Target CSV path; defaults to data/activities.csv.
     * @return int The number of rows appended.
     */
    public function appendActivitiesToCsv(array $activities, ?string $path = null): int
    {
        if ($activities === []) {
            return 0;
        }

        $path ??= base_path('data/activities.csv');

        if (! is_file($path)) {
            return 0;
        }

        $readHandle = fopen($path, 'r');
        $headers = fgetcsv($readHandle);
        fclose($readHandle);

        if (! is_array($headers)) {
            return 0;
        }

        usort($activities, fn (Activity $first, Activity $second): int => $first->occurred_at <=> $second->occurred_at);

        $writeHandle = fopen($path, 'a');

        foreach ($activities as $activity) {
            fputcsv($writeHandle, $this->csvRow($activity, $headers));
        }

        fclose($writeHandle);

        return count($activities);
    }

    /**
     * Map an activity to a CSV row in the given header order.
     *
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    public function csvRow(Activity $activity, array $headers): array
    {
        return array_map(function (string $column) use ($activity): string {
            if ($column === 'occurred_at') {
                return $activity->occurred_at?->format('Y-m-d H:i:s') ?? '';
            }

            $value = $activity->getAttribute($column);

            // meta and the stream columns (heart_rate/altitude/speed/track) are
            // array casts; encode any array column rather than stringifying it.
            if (is_array($value)) {
                return json_encode($value) ?: '';
            }

            return (string) ($value ?? '');
        }, $headers);
    }

    /**
     * Extract the IANA timezone name from Strava's "(GMT+00:00) Europe/London" format.
     */
    private function ianaTimezone(?string $stravaTimezone): ?string
    {
        if (! $stravaTimezone) {
            return null;
        }

        return Str::afterLast($stravaTimezone, ' ') ?: null;
    }
}
