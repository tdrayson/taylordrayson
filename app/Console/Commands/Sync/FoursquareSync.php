<?php

namespace App\Console\Commands\Sync;

use App\Actions\Checkins\ImportCheckin;
use App\Enums\Source;
use App\Jobs\GenerateEntryMap;
use App\Models\Checkin;
use App\Services\Foursquare;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('foursquare:sync {--days=2 : Days back to re-check for new check-ins}')]
#[Description('Sync new Foursquare/Swarm check-ins to the timeline')]
class FoursquareSync extends Command
{
    /**
     * Upper bound on the automatic catch-up: if the newest stored check-in is
     * older than this, only the most recent window is fetched. A longer gap is
     * a job for `foursquare:import`, which pages the whole history.
     */
    private const MAX_CATCHUP_DAYS = 90;

    /**
     * Fetch only check-ins newer than the last one stored, so a run that finds
     * nothing new costs a single empty page rather than paging all history the
     * way `foursquare:import` does.
     *
     * The window is the last --days, extended back to the newest stored
     * check-in when that is older, so a gap opened by a missed schedule
     * self-heals instead of being stranded behind a fixed window. Overlap is
     * deliberate and free: the action upserts on source_id, so re-seeing a
     * check-in updates it rather than duplicating it, which also picks up a
     * shout or photo added after the fact.
     */
    public function handle(Foursquare $foursquare, ImportCheckin $importCheckin): int
    {
        $after = $this->resolveAfterTimestamp();

        $imported = 0;
        $updated = 0;
        $photosAdded = 0;

        try {
            foreach ($foursquare->checkins($after) as $item) {
                $result = $importCheckin($item);

                $result->created ? $imported++ : $updated++;
                $photosAdded += $result->photosAdded;

                foreach ($result->warnings as $warning) {
                    $this->warn("  {$warning}");
                }

                if ($result->created) {
                    // Queue the pin now rather than leaving it to the next
                    // maps:generate sweep, so a check-in reaches the timeline
                    // looking finished. Only for new ones: an existing
                    // check-in already has its map.
                    GenerateEntryMap::dispatch($result->checkin);

                    $this->info(sprintf('%s - %s', $result->checkin->venue_name, date('Y-m-d H:i', $item['createdAt'])));
                }
            }
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Synced {$imported} new check-in(s), {$updated} already stored, {$photosAdded} photo(s) attached.");

        return self::SUCCESS;
    }

    /**
     * The unix timestamp to ask Foursquare for check-ins after, or null when
     * nothing is stored yet and the whole (short) window is wanted.
     */
    private function resolveAfterTimestamp(): ?int
    {
        $window = Carbon::now()->subDays(max(0, (int) $this->option('days')));

        /** @var string|null $newest */
        $newest = Checkin::query()->where('source', Source::Swarm->value)->max('occurred_at');

        if ($newest === null) {
            return $window->timestamp;
        }

        $healFrom = Carbon::parse($newest);
        $cap = Carbon::now()->subDays(self::MAX_CATCHUP_DAYS);

        if ($healFrom->lt($cap)) {
            $this->warn(sprintf('Newest check-in predates %s; catching up only that far. Run foursquare:import to backfill older history.', $cap->toDateString()));
            $healFrom = $cap;
        }

        return $healFrom->min($window)->timestamp;
    }
}
