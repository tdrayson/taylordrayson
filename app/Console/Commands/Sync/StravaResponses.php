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
    /**
     * Every request here is a read, and reads have their own ceiling of 100 per
     * 15 minutes, half the overall 200. The old 150 was measured against the
     * wrong limit, so a backfill reliably 429'd the other syncs for the rest of
     * the window. 90 matches {@see BackfillStravaDescriptions}.
     */
    private const MAX_REQUESTS = 90;

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
        $resumeAt = $all ? Cache::get(self::CURSOR) : null;
        $resuming = $resumeAt !== null;

        $spent = 0;
        $pulled = 0;
        $stoppedAt = null;

        foreach ($this->summaries($strava, $after, $spent) as $summary) {
            $sourceId = (string) $summary['id'];

            // Summaries come back newest first, so everything above the cursor
            // was done on an earlier run. The cursor itself is the activity
            // that run stopped at without pulling, so it is where this one
            // starts rather than the last one to skip.
            if ($resuming) {
                if ($sourceId !== $resumeAt) {
                    continue;
                }

                $resuming = false;
            }

            $activity = $stored->get($sourceId);

            if ($activity === null) {
                continue;
            }

            $heldFor = $held->get($activity->id, ['like' => 0, 'reply' => 0]);

            if ($this->skippable($summary, $heldFor, $all)) {
                continue;
            }

            // Kudos always, comments only where there are any to find. Costing
            // the activity before committing to it is what keeps the ceiling
            // honest: assuming two would stop the walk early and strand a
            // cursor on an activity there was budget for.
            $withComments = $this->needsComments($summary, $heldFor);
            $cost = $withComments ? 2 : 1;

            // Stopping mid-list is fine as long as we say where we stopped, or
            // the next --all starts over and never reaches the older half.
            if ($spent + $cost > self::MAX_REQUESTS) {
                $stoppedAt = $sourceId;
                break;
            }

            $pull($activity, $withComments);
            $spent += $cost;
            $pulled++;
        }

        // The stream ended without ever meeting the id we were told to resume
        // at: it's gone, or the walk changed shape. Holding onto it would
        // stall every future backfill, so it is dropped rather than kept.
        if ($resumeAt !== null && $resuming) {
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
     * Whether this activity can be passed over without spending its two
     * detail requests.
     *
     * A windowed run trusts the counts: matching them means nothing has
     * happened since the last run. --all deliberately does not, because
     * content changes without the counts moving, an edited comment or a
     * renamed athlete, and repairing exactly that is what it is for.
     *
     * The one thing --all can still skip is a provable no-op: Strava reports
     * nothing and we hold nothing, so there is no content to refresh and no
     * stale row to clear. Fetching it can only ever confirm two empty sets.
     * Anything else, including a 0/0 summary against rows we still hold, must
     * be fetched so the reconcile can delete them.
     *
     * @param  array<string, mixed>  $summary
     * @param  array{like: int, reply: int}  $held
     */
    private function skippable(array $summary, array $held, bool $all): bool
    {
        if (! $all) {
            return ! $this->differs($summary, $held);
        }

        return (int) ($summary['kudos_count'] ?? 0) === 0
            && (int) ($summary['comment_count'] ?? 0) === 0
            && $held['like'] === 0
            && $held['reply'] === 0;
    }

    /**
     * Whether this activity's comments endpoint is worth a request.
     *
     * Kudos are near-universal and comments are rare, so this is where the
     * backfill's cost actually sits. Strava's summary count is authoritative
     * for "are there any", and a count of zero against replies we still hold
     * is a withdrawal that has to be fetched so the reconcile can clear them.
     *
     * @param  array<string, mixed>  $summary
     * @param  array{like: int, reply: int}  $held
     */
    private function needsComments(array $summary, array $held): bool
    {
        return (int) ($summary['comment_count'] ?? 0) > 0 || $held['reply'] > 0;
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

            // A short page is the last page. Waiting for an empty one instead
            // spent a second read on every run.
            if (count($summaries) < self::PER_PAGE) {
                return;
            }
        }
    }
}
