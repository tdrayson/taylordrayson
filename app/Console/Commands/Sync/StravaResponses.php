<?php

namespace App\Console\Commands\Sync;

use App\Actions\Syndicated\PullStravaResponses;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Activity;
use App\Models\SyndicatedResponse;
use App\Services\Strava\Client;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[Signature('strava:responses {--days=7 : How many days back to check} {--all : Every activity ever, repairing anything stale}')]
#[Description('Pull the kudos and comments left on Strava onto the activities they belong to')]
class StravaResponses extends Command
{
    /** Strava allows 200 requests per 15 minutes, and the syncs want some too. */
    private const MAX_REQUESTS = 150;

    /** Where the last unfinished backfill stopped. */
    private const CURSOR = 'strava:responses:cursor';

    private const PER_PAGE = 100;

    /**
     * Upper bound on the automatic catch-up: if the newest stored response is
     * older than this, only the most recent window is fetched. A longer gap is
     * a job for --all, not a cron run.
     */
    private const MAX_CATCHUP_DAYS = 90;

    public function handle(Client $strava, PullStravaResponses $pull): int
    {
        if (! $strava->token()) {
            $this->error('Could not obtain a Strava access token.');

            return self::FAILURE;
        }

        $all = (bool) $this->option('all');
        $after = $all ? null : $this->resolveAfterTimestamp();

        $stored = Activity::query()
            ->where('source', Source::Strava->value)
            ->whereNotNull('source_id')
            ->get()
            ->keyBy('source_id');

        $held = $this->heldCounts();

        // Where the last backfill ran out of requests. Only --all uses it: a
        // windowed run is small enough to finish, and would otherwise skip the
        // newest activities to resume an old walk.
        $resumeAfter = $all ? Cache::get(self::CURSOR) : null;
        $resuming = $resumeAfter !== null;

        $spent = 0;
        $pulled = 0;
        $stoppedAt = null;

        foreach ($this->summaries($strava, $after, $spent) as $summary) {
            $sourceId = (string) $summary['id'];

            // Summaries come back newest first, so everything down to the
            // cursor was done on an earlier run.
            if ($resuming) {
                $resuming = $sourceId !== $resumeAfter;

                continue;
            }

            $activity = $stored->get($sourceId);

            if ($activity === null) {
                continue;
            }

            if (! $all && ! $this->differs($summary, $held->get($activity->id, ['like' => 0, 'reply' => 0]))) {
                continue;
            }

            // Two requests per activity. Stopping mid-list is fine as long as
            // we say where we stopped, or the next --all starts over and never
            // reaches the older half.
            if ($spent + 2 > self::MAX_REQUESTS) {
                $stoppedAt = $sourceId;
                break;
            }

            $pull($activity);
            $spent += 2;
            $pulled++;
        }

        // The stream ended without ever meeting the id we were told to resume
        // past: it's gone, or the walk changed shape. Holding onto it would
        // stall every future backfill, so it is dropped rather than kept.
        if ($resumeAfter !== null && $resuming) {
            $stoppedAt = null;
            $this->warn('The stored cursor never turned up; found nothing to resume from. Starting from the top next run.');
        }

        $this->rememberCursor($all, $stoppedAt);

        if ($stoppedAt !== null) {
            $this->warn('Stopped at the request ceiling. Run again to continue.');
        }

        $this->info("Done. Pulled responses for {$pulled} activities.");

        return self::SUCCESS;
    }

    /**
     * Hold the resume point for the next backfill, or clear it once a walk has
     * run to the end.
     */
    private function rememberCursor(bool $all, ?string $stoppedAt): void
    {
        if (! $all) {
            return;
        }

        $stoppedAt === null
            ? Cache::forget(self::CURSOR)
            : Cache::put(self::CURSOR, $stoppedAt, now()->addWeek());
    }

    /**
     * The unix timestamp to ask Strava for activities after. Normally --days
     * back, but the window stretches to the newest response we already hold
     * when a missed run has opened a longer gap, so a cron outage does not
     * strand an activity's kudos and comments permanently.
     */
    private function resolveAfterTimestamp(): int
    {
        $window = now()->subDays(max(0, (int) $this->option('days')));

        /** @var string|null $newest */
        $newest = SyndicatedResponse::query()->where('source', Source::Strava->value)->max('occurred_at');

        if ($newest === null) {
            return $window->timestamp;
        }

        $healFrom = Carbon::parse($newest);
        $cap = now()->subDays(self::MAX_CATCHUP_DAYS);

        if ($healFrom->lt($cap)) {
            $this->warn(sprintf('Newest response predates %s; catching up only that far.', $cap->toDateString()));
            $healFrom = $cap;
        }

        return $healFrom->min($window)->timestamp;
    }

    /**
     * How many likes and replies we already hold per activity, counted rather
     * than stored so it can't drift out of step with the rows themselves.
     *
     * @return Collection<int, array{like: int, reply: int}>
     */
    private function heldCounts(): Collection
    {
        // toBase(), so `kind` comes back as the string it is stored as rather
        // than the enum the model would cast it to, which a raw select has no
        // business carrying.
        return SyndicatedResponse::query()
            ->toBase()
            ->selectRaw('target_id, kind, count(*) as total')
            ->where('target_type', (new Activity)->getMorphClass())
            ->where('source', Source::Strava->value)
            ->groupBy('target_id', 'kind')
            ->get()
            ->groupBy('target_id')
            ->map(fn (Collection $rows): array => [
                'like' => (int) ($rows->firstWhere('kind', WebmentionKind::Like->value)?->total ?? 0),
                'reply' => (int) ($rows->firstWhere('kind', WebmentionKind::Reply->value)?->total ?? 0),
            ]);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array{like: int, reply: int}  $held
     */
    private function differs(array $summary, array $held): bool
    {
        return (int) ($summary['kudos_count'] ?? 0) !== $held['like']
            || (int) ($summary['comment_count'] ?? 0) !== $held['reply'];
    }

    /**
     * Every summary in the window, newest first. A page fetch spends from the
     * same budget as a pull, so a long walk stops paging rather than overrun it.
     *
     * @param  int  $spent  Passed by reference: incremented per page fetched.
     * @return iterable<array<string, mixed>>
     */
    private function summaries(Client $strava, ?int $after, int &$spent): iterable
    {
        for ($page = 1; ; $page++) {
            if ($spent + 1 > self::MAX_REQUESTS) {
                return;
            }

            $summaries = $strava->activitiesPage($page, self::PER_PAGE, $after);
            $spent++;

            if (blank($summaries)) {
                return;
            }

            yield from $summaries;
        }
    }
}
