<?php

namespace App\Console\Commands\Sync;

use App\Actions\Strava\StoreStravaActivity;
use App\Data\StoredStravaActivity;
use App\Enums\Source;
use App\Models\Activity;
use App\Services\Strava\Client;
use App\Support\StravaActivityType;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('strava:sync {--days=7 : How many days back to check for new activities} {--refresh : Re-fetch every activity in the window, not only those whose summary changed}')]
#[Description('Sync new Client activities to the database, and pick up edits to the ones already stored')]
class StravaSync extends Command
{
    /**
     * Upper bound on the automatic catch-up: if the newest stored activity is
     * older than this, only the most recent window is fetched. A longer gap is
     * a job for a deliberate backfill, not a cron run.
     */
    private const MAX_CATCHUP_DAYS = 90;

    private const PER_PAGE = 200;

    public function handle(Client $strava, StoreStravaActivity $store): int
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

        $created = 0;

        foreach ($newActivities as $stravaActivity) {
            $detail = $strava->activity($stravaActivity['id']);

            if (! $detail) {
                $this->warn("Failed to fetch activity {$stravaActivity['id']}");

                continue;
            }

            $result = $store($detail);
            $created++;

            if ($result->adopted) {
                $this->line('  → Adopted the Setgraph workout logged at '.$result->activity->getOriginal('occurred_at'));
            }

            $this->info("[{$created}] ".$result->activity->name.$this->photoSuffix($result));
        }

        $refreshed = $this->refreshExisting($strava, $store, $this->withinWindow($existing->all()), $stored);

        $this->info('Done. Synced '.$created.' activities, refreshed '.$refreshed.'.');

        return self::SUCCESS;
    }

    /**
     * Pick up the edits made after Strava auto-published an activity: a better
     * title, a description written later, photos added from the phone.
     *
     * Webhook events cover most of this now, so a cron run is a safety net for
     * what a missed event dropped. The summary already in hand is compared
     * against the stored row first, so a run where nothing changed still costs
     * the one page it always did. Only an activity that differs is worth the
     * detail request, which is also what carries the description, since the
     * summary omits it. A description edited on its own is invisible here, and
     * is what `--refresh` is for.
     *
     * @param  array<int, array<string, mixed>>  $summaries  Summaries whose activity is already stored.
     * @param  Collection<string, Activity>  $stored  Those activities, keyed by source id.
     * @return int The number re-fetched.
     */
    private function refreshExisting(Client $strava, StoreStravaActivity $store, array $summaries, Collection $stored): int
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

            $result = $store($detail);
            $refreshed++;

            $fields = $result->changed === [] ? '' : ' ('.implode(', ', $result->changed).')';
            $this->info('  ↻ '.$result->activity->name.$fields.$this->photoSuffix($result));
        }

        return $refreshed;
    }

    private function photoSuffix(StoredStravaActivity $result): string
    {
        return $result->photos > 0 ? " [{$result->photos} photo(s)]" : '';
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
            || $activity->type !== StravaActivityType::for($summary)
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
     * Every summary after the given timestamp.
     *
     * A short page is the last page. Waiting for an empty one instead spent a
     * second request on every run, which against a 1000-read daily budget was
     * the single largest line on the schedule.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchActivities(Client $strava, int $after): ?array
    {
        $activities = [];

        for ($page = 1; ; $page++) {
            $batch = $strava->activitiesPage($page, self::PER_PAGE, $after);

            if ($batch === null) {
                $this->error('Failed to fetch activities from Strava.');

                return null;
            }

            $activities = array_merge($activities, $batch);

            if (count($batch) < self::PER_PAGE) {
                return $activities;
            }
        }
    }
}
