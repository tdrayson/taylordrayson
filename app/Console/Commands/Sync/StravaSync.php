<?php

namespace App\Console\Commands\Sync;

use App\Actions\StoreActivityStreams;
use App\Actions\SyncStravaPhotos;
use App\Actions\Workouts\RecordSetgraphWorkout;
use App\Enums\Source;
use App\Jobs\GenerateEntryMap;
use App\Models\Activity;
use App\Services\Strava;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Signature('strava:sync {--days=7 : How many days back to check for new activities} {--refresh : Re-fetch every activity in the window, not only those whose summary changed}')]
#[Description('Sync new Strava activities to the database, and pick up edits to the ones already stored')]
class StravaSync extends Command
{
    /**
     * Upper bound on the automatic catch-up: if the newest stored activity is
     * older than this, only the most recent window is fetched. A longer gap is
     * a job for a deliberate backfill, not a cron run.
     */
    private const MAX_CATCHUP_DAYS = 90;

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

        $after = $this->resolveAfterTimestamp();

        $stravaActivities = $this->fetchActivities($strava, $after);

        if ($stravaActivities === null) {
            return self::FAILURE;
        }

        $stored = Activity::query()
            ->where('source', Source::Strava->value)
            ->whereNotNull('source_id')
            ->get()
            ->keyBy('source_id');

        [$existing, $newActivities] = collect($stravaActivities)
            ->partition(fn (array $a): bool => $stored->has((string) $a['id']));

        $this->info("Found {$newActivities->count()} new activities to sync.");

        $created = [];

        foreach ($newActivities as $stravaActivity) {
            $detail = $strava->activity($stravaActivity['id']);
            if (! $detail) {
                continue;
            }

            $activity = $this->createActivity($detail);
            $this->downloadPhotos($strava, $detail, $activity);
            GenerateEntryMap::dispatch($activity);
            app(StoreActivityStreams::class)($activity, $strava);

            $created[] = $activity;
            $this->info('['.count($created).'] '.$activity->name);
        }

        $appended = $this->appendActivitiesToCsv($created);

        if ($appended > 0) {
            $this->info("Appended {$appended} row(s) to data/activities.csv.");
        }

        $refreshed = $this->refreshExisting($strava, $this->withinWindow($existing->all()), $stored);

        $this->info('Done. Synced '.count($created).' activities, refreshed '.$refreshed.'.');

        return self::SUCCESS;
    }

    /**
     * Pick up the edits made after Strava auto-published an activity: a better
     * title, a description written later, photos added from the phone.
     *
     * The summary already in hand is compared against the stored row first, so
     * a run where nothing changed still costs the one page it always did. Only
     * an activity that differs is worth the detail request, which is also what
     * carries the description, since the summary omits it. A description edited
     * on its own is invisible here, and is what `--refresh` is for.
     *
     * @param  array<int, array<string, mixed>>  $summaries  Summaries whose activity is already stored.
     * @param  Collection<string, Activity>  $stored  Those activities, keyed by source id.
     * @return int The number re-fetched.
     */
    private function refreshExisting(Strava $strava, array $summaries, Collection $stored): int
    {
        $force = (bool) $this->option('refresh');
        $refreshed = 0;

        foreach ($summaries as $summary) {
            $activity = $stored->get((string) $summary['id']);

            if ($activity === null || (! $force && ! $this->hasChanged($summary, $activity))) {
                continue;
            }

            $detail = $strava->activity($summary['id']);

            if (! $detail) {
                $this->warn("Failed to re-fetch activity {$summary['id']}");

                continue;
            }

            $polylineBefore = $activity->meta['polyline'] ?? null;

            $activity->fill($this->attributesFor($detail));
            $activity->meta = $this->mergedMeta($this->metaFor($detail), $activity);

            $changed = array_keys($activity->getDirty());
            $activity->save();

            $this->downloadPhotos($strava, $detail, $activity);

            // The route drives the stored map image, so a corrected GPS trace
            // has to redraw it rather than keep the one it came in with.
            if (($activity->meta['polyline'] ?? null) !== $polylineBefore) {
                GenerateEntryMap::dispatch($activity);
            }

            $refreshed++;
            $this->info('  ↻ '.$activity->name.($changed === [] ? '' : ' ('.implode(', ', $changed).')'));
        }

        return $refreshed;
    }

    /**
     * The summaries inside --days, which the refresh pass is deliberately held
     * to rather than following the self-heal stretch in
     * {@see resolveAfterTimestamp()}. That stretch exists so a missed run does
     * not strand a *new* activity; letting it widen the refresh as well would
     * mean a cron outage came back and re-fetched up to 90 days of details in
     * one go. Refreshing further back is a repair, and says so: --days=30.
     *
     * @param  array<int, array<string, mixed>>  $summaries
     * @return array<int, array<string, mixed>>
     */
    private function withinWindow(array $summaries): array
    {
        $from = Carbon::now()->subDays(max(0, (int) $this->option('days')));

        return array_values(array_filter($summaries, function (array $summary) use ($from): bool {
            $start = $summary['start_date'] ?? $summary['start_date_local'] ?? null;

            return $start !== null && Carbon::parse($start)->gte($from);
        }));
    }

    /**
     * Whether the summary disagrees with the row we stored, across the fields
     * the summary carries. Photos count as a change when Strava holds more than
     * we have downloaded.
     *
     * @param  array<string, mixed>  $summary
     */
    private function hasChanged(array $summary, Activity $activity): bool
    {
        $distance = ($summary['distance'] ?? null) ? (int) round($summary['distance']) : null;
        $photos = $activity->getMedia('cover')->count() + $activity->getMedia('photos')->count();

        return $activity->name !== ($summary['name'] ?? null)
            || $activity->type !== $this->typeFor($summary)
            || (int) $activity->duration !== (int) ($summary['moving_time'] ?? 0)
            || $activity->distance !== $distance
            || ($summary['total_photo_count'] ?? 0) > $photos;
    }

    /**
     * The unix timestamp to ask Strava for activities after. Normally --days
     * back, but the window stretches to the newest stored activity when a
     * missed run has opened a longer gap, so a cron outage does not strand
     * activities permanently. The overlap is close to free: an activity already
     * stored costs a detail request only when its summary has changed.
     */
    private function resolveAfterTimestamp(): int
    {
        $window = Carbon::now()->subDays(max(0, (int) $this->option('days')));

        /** @var string|null $newest */
        $newest = Activity::query()->where('source', Source::Strava->value)->max('occurred_at');

        if ($newest === null) {
            return $window->timestamp;
        }

        // occurred_at is local wall-clock, so anchor to the start of that day:
        // no timezone offset can then push the boundary past a real activity.
        $healFrom = Carbon::parse($newest)->startOfDay();
        $cap = Carbon::now()->subDays(self::MAX_CATCHUP_DAYS);

        if ($healFrom->lt($cap)) {
            $this->warn(sprintf('Newest activity predates %s; catching up only that far.', $cap->toDateString()));
            $healFrom = $cap;
        }

        return $healFrom->min($window)->timestamp;
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
        $attributes = $this->attributesFor($data);
        $meta = $this->metaFor($data);

        $activity = $this->unclaimedSetgraphActivity(Carbon::parse($attributes['occurred_at'], 'UTC'));

        if ($activity === null) {
            return Activity::create([...$attributes, 'meta' => $meta ?: null]);
        }

        // Setgraph logged this session before Strava had it. Take the row over
        // rather than creating a second one, keeping the sets it recorded.
        $activity->fill($attributes);
        $activity->meta = $this->mergedMeta($meta, $activity);
        $activity->save();

        $this->line('  → Adopted the Setgraph workout logged at '.$activity->getOriginal('occurred_at'));

        return $activity;
    }

    /**
     * Our column values for a Strava payload. `description` and `calories` are
     * detail-only, so a summary maps to null for both rather than to nothing.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesFor(array $data): array
    {
        // Strava's start_date_local carries a Z; parse as UTC so the wall-clock digits are kept verbatim.
        $occurredAt = Carbon::parse($data['start_date_local'] ?? $data['start_date'], 'UTC');

        return [
            'occurred_at' => $occurredAt->format('Y-m-d H:i:s'),
            'type' => $this->typeFor($data),
            'name' => $data['name'],
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'duration' => $data['moving_time'],
            'calories' => ($data['calories'] ?? null) ?: null,
            'distance' => $data['distance'] ? (int) round($data['distance']) : null,
            'average_heart_rate' => $data['average_heartrate'] ?? null,
            'max_heart_rate' => $data['max_heartrate'] ?? null,
            'source' => Source::Strava->value,
            'source_id' => (string) $data['id'],
            'timezone' => $this->ianaTimezone($data['timezone'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function metaFor(array $data): array
    {
        $meta = array_filter([
            'elapsed_time' => $data['elapsed_time'] ?? null,
            'total_elevation_gain' => $data['total_elevation_gain'] ?? null,
            'elev_high' => $data['elev_high'] ?? null,
            'elev_low' => $data['elev_low'] ?? null,
            'max_speed' => $data['max_speed'] ?? null,
            'average_speed' => $data['average_speed'] ?? null,
            'average_cadence' => $data['average_cadence'] ?? null,
            'sport_type' => $data['sport_type'] ?? $data['type'] ?? 'Workout',
        ], fn ($v) => $v !== null && $v !== 0 && $v !== 0.0);

        if ($polyline = $data['map']['polyline'] ?? null) {
            $meta['polyline'] = $polyline;
        }

        return $meta;
    }

    /**
     * Strava's meta over the row's, keeping the keys Strava knows nothing about.
     * `sets` is Setgraph's, and a re-sync must not drop it.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function mergedMeta(array $meta, Activity $activity): array
    {
        return [...$meta, ...array_filter(
            $activity->meta ?? [],
            fn (string $key): bool => $key === 'sets',
            ARRAY_FILTER_USE_KEY,
        )];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function typeFor(array $data): string
    {
        $sportType = $data['sport_type'] ?? $data['type'] ?? 'Workout';

        return self::TYPE_MAP[$sportType] ?? Str::kebab($sportType);
    }

    /**
     * A strength activity Setgraph created that no Strava activity has claimed
     * yet, within {@see RecordSetgraphWorkout::MATCH_WINDOW_MINUTES} of this one.
     */
    private function unclaimedSetgraphActivity(Carbon $occurredAt): ?Activity
    {
        $window = RecordSetgraphWorkout::MATCH_WINDOW_MINUTES;

        return Activity::query()
            ->where('source', Source::Setgraph->value)
            ->whereNull('source_id')
            ->whereBetween('occurred_at', [
                $occurredAt->copy()->subMinutes($window)->format('Y-m-d H:i:s'),
                $occurredAt->copy()->addMinutes($window)->format('Y-m-d H:i:s'),
            ])
            ->get()
            ->sortBy(fn (Activity $activity): int => abs($activity->occurred_at->diffInSeconds($occurredAt)))
            ->first();
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
