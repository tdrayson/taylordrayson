<?php

namespace App\Actions\Strava;

use App\Actions\Workouts\RecordSetgraphWorkout;
use App\Data\StoredStravaActivity;
use App\Enums\Source;
use App\Jobs\GenerateEntryMap;
use App\Models\Activity;
use App\Services\Strava\Client;
use App\Support\EntryInstant;
use App\Support\StravaActivityType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Store one Strava activity detail payload, creating the row or bringing the
 * stored one back in line with it, then fetching what the detail only points
 * at: photos, the map, the charts.
 *
 * Shared by the webhook job and `strava:sync`, which is the point: an activity
 * that arrives by push and one found by the daily safety net have to land
 * identically, and did not when each built its own row.
 */
class StoreStravaActivity
{
    public function __construct(
        private readonly Client $strava,
        private readonly SyncStravaPhotos $syncPhotos,
        private readonly StoreActivityStreams $storeStreams,
    ) {}

    /**
     * @param  array<string, mixed>  $detail  Strava's detailed representation, not a summary.
     */
    public function __invoke(array $detail): StoredStravaActivity
    {
        $attributes = $this->attributesFor($detail);

        $activity = $this->stored((string) $detail['id']);
        $adopted = false;

        if ($activity === null) {
            $activity = $this->unclaimedSetgraphActivity(Carbon::parse($attributes['occurred_at'], 'UTC'));
            $adopted = $activity !== null;
        }

        $created = $activity === null;
        $activity ??= new Activity;

        $polylineBefore = $activity->meta['polyline'] ?? null;

        $activity->fill($attributes);
        $activity->meta = $this->mergedMeta($this->metaFor($detail), $activity) ?: null;

        $changed = array_keys($activity->getDirty());
        $activity->save();

        $photos = $this->downloadPhotos($detail, $activity);

        // The route drives the stored map image, so a corrected GPS trace has
        // to redraw it rather than keep the one it came in with.
        if (($activity->meta['polyline'] ?? null) !== $polylineBefore) {
            GenerateEntryMap::dispatch($activity);
        }

        $this->storeStreams($activity);

        return new StoredStravaActivity($activity, $created, $adopted, $changed, $photos);
    }

    private function stored(string $sourceId): ?Activity
    {
        return Activity::query()
            ->where('source', Source::Strava->value)
            ->where('source_id', $sourceId)
            ->first();
    }

    /**
     * The charts, which are the one part of an activity the detail payload does
     * not carry. Only fetched where there is a route to plot and no series
     * stored yet, which is the same condition `strava:streams` filters on, so a
     * webhook that arrives before Strava has finished processing the upload is
     * repaired by that pass rather than stranded.
     */
    private function storeStreams(Activity $activity): void
    {
        if (blank($activity->meta['polyline'] ?? null) || filled($activity->altitude)) {
            return;
        }

        ($this->storeStreams)($activity, $this->strava);
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return int How many photos were stored.
     */
    private function downloadPhotos(array $detail, Activity $activity): int
    {
        if (($detail['total_photo_count'] ?? 0) === 0) {
            return 0;
        }

        $photos = $this->strava->activityPhotos($detail['id']);

        if ($photos === null) {
            Log::warning('strava photos fetch failed', ['activity' => $detail['id']]);

            return 0;
        }

        return ($this->syncPhotos)($activity, $photos);
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
            'type' => StravaActivityType::for($data),
            'name' => $data['name'],
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'duration' => $data['moving_time'],
            'calories' => ($data['calories'] ?? null) ?: null,
            'distance' => $data['distance'] ? (int) round($data['distance']) : null,
            'average_heart_rate' => $data['average_heartrate'] ?? null,
            'max_heart_rate' => $data['max_heartrate'] ?? null,
            'source' => Source::Strava->value,
            'source_id' => (string) $data['id'],
            'timezone' => $this->timezoneFor($data),
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
     * The zone the activity happened in, or null where Strava is guessing.
     *
     * Without GPS, Strava names the first IANA zone matching the device's UTC
     * offset, so an indoor workout comes back as Africa/Algiers for BST or
     * Africa/Abidjan for GMT. The offset is right and the place is fiction, and
     * a zone that names the wrong continent cannot be reasoned about or
     * corrected: #284's backfill only overrides a zone that is empty or home.
     * Null instead, which already means home, and let a flight prove otherwise.
     *
     * @param  array<string, mixed>  $data
     */
    private function timezoneFor(array $data): ?string
    {
        $timezone = $this->ianaTimezone($data['timezone'] ?? null);

        if ($timezone === null || $timezone === EntryInstant::HOME) {
            return $timezone;
        }

        return blank($data['map']['polyline'] ?? null) ? null : $timezone;
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
