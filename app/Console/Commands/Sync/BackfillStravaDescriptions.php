<?php

namespace App\Console\Commands\Sync;

use App\Enums\Source;
use App\Models\Activity;
use App\Services\Strava;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

#[Signature('strava:backfill-descriptions {--limit=90 : Max activities to fetch this run, kept under the 100-request/15-min read limit} {--sleep=0 : Seconds to pause between requests} {--restart : Ignore the saved cursor and start from the oldest activity} {--skip-export= : Path to the old-site export CSV; its activities are skipped so only data not already in it is fetched}')]
#[Description('Backfill the description on existing Strava activities from the detail endpoint, resuming where the last run left off')]
class BackfillStravaDescriptions extends Command
{
    private const CURSOR_KEY = 'strava:desc-backfill:cursor';

    /**
     * Strava ids covered by --skip-export, excluded from the fetch.
     *
     * @var list<string>
     */
    private array $skipIds = [];

    /**
     * Stopping after this many consecutive failures: the detail endpoint rarely
     * fails in isolation, so a run of them almost always means we have been rate
     * limited rather than that each activity is genuinely gone.
     */
    private const MAX_CONSECUTIVE_FAILURES = 5;

    /**
     * Walk existing Strava activities oldest-first and store each description.
     * Resumes from a cached cursor on the activity id; --restart clears it. Run
     * `export:csv activity data/activities.csv` afterwards to refresh the seed.
     */
    public function handle(Strava $strava): int
    {
        if (! $strava->token()) {
            $this->error('Could not obtain a Strava access token.');

            return self::FAILURE;
        }

        if ($this->option('restart')) {
            Cache::forget(self::CURSOR_KEY);
        }

        if ($export = $this->option('skip-export')) {
            if (! is_file($export)) {
                $this->error("Export file not found: {$export}");

                return self::FAILURE;
            }

            $this->skipIds = $this->stravaIdsFromExport($export);
            $this->info('Skipping '.count($this->skipIds).' activity(ies) already in the export.');
        }

        $cursor = (int) Cache::get(self::CURSOR_KEY, 0);
        $limit = max(1, (int) $this->option('limit'));
        $sleep = max(0, (int) $this->option('sleep'));

        $activities = $this->remaining($cursor)->limit($limit)->get();

        if ($activities->isEmpty()) {
            $this->info('Nothing left to backfill.');

            return self::SUCCESS;
        }

        $processed = 0;
        $withText = 0;
        $consecutiveFailures = 0;
        $cursorBeforeStreak = $cursor;

        foreach ($activities as $activity) {
            $detail = $strava->activity($activity->source_id);

            if ($detail === null) {
                if ($consecutiveFailures === 0) {
                    $cursorBeforeStreak = $cursor;
                }

                $consecutiveFailures++;
                $this->warn("Failed to fetch activity {$activity->source_id} (id {$activity->id}).");

                if ($consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES) {
                    Cache::put(self::CURSOR_KEY, $cursorBeforeStreak);
                    $this->error('Too many consecutive failures, likely rate limited. Stopping; re-run to continue.');

                    return $this->report($processed, $withText, $cursorBeforeStreak);
                }

                $cursor = $activity->id;
                Cache::put(self::CURSOR_KEY, $cursor);

                continue;
            }

            $consecutiveFailures = 0;
            $description = trim((string) ($detail['description'] ?? '')) ?: null;
            $activity->update(['description' => $description]);

            $processed++;
            if ($description !== null) {
                $withText++;
            }

            $cursor = $activity->id;
            Cache::put(self::CURSOR_KEY, $cursor);

            if ($sleep > 0) {
                sleep($sleep);
            }
        }

        return $this->report($processed, $withText, $cursor);
    }

    /**
     * Existing Strava activities past the cursor, oldest id first.
     *
     * @return Builder<Activity>
     */
    private function remaining(int $cursor): Builder
    {
        return Activity::query()
            ->where('source', Source::Strava->value)
            ->whereNotNull('source_id')
            ->whereNull('description')
            ->where('id', '>', $cursor)
            ->when($this->skipIds !== [], fn (Builder $query): Builder => $query->whereNotIn('source_id', $this->skipIds))
            ->orderBy('id');
    }

    /**
     * Every Strava id present in the old-site export, regardless of whether it
     * carried a description, so the backfill can leave those activities alone.
     *
     * @return list<string>
     */
    private function stravaIdsFromExport(string $file): array
    {
        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle, 0, ',', '"', '');

        if (! is_array($headers)) {
            fclose($handle);

            return [];
        }

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $ids = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }

            $data = json_decode((string) array_combine($headers, $row)['Activity data'], true);
            $stravaId = is_array($data) ? ($data['Activity ID'] ?? null) : null;

            if ($stravaId) {
                $ids[] = (string) $stravaId;
            }
        }

        fclose($handle);

        return array_values(array_unique($ids));
    }

    private function report(int $processed, int $withText, int $cursor): int
    {
        $remaining = $this->remaining($cursor)->count();

        $this->info("Fetched {$processed} activity(ies); {$withText} had a description. {$remaining} remaining.");

        return self::SUCCESS;
    }
}
