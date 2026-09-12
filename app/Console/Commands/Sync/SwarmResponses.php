<?php

namespace App\Console\Commands\Sync;

use App\Actions\Syndicated\PullSwarmResponses;
use App\Enums\Source;
use App\Models\Checkin;
use App\Models\SyndicatedResponse;
use App\Services\Foursquare\Client;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('swarm:responses {--days=7 : How many days back to check} {--all : Every check-in ever, repairing anything stale}')]
#[Description('Pull the likes and comments left on Swarm onto the check-ins they belong to')]
class SwarmResponses extends Command
{
    /**
     * Upper bound on the automatic catch-up: if the newest stored response is
     * older than this, only the most recent window is fetched. A longer gap is
     * a job for --all, not a cron run.
     */
    private const MAX_CATCHUP_DAYS = 90;

    public function handle(Client $swarm, PullSwarmResponses $pull): int
    {
        $all = (bool) $this->option('all');
        $after = $all ? null : $this->resolveAfterTimestamp();

        $stored = Checkin::query()
            ->where('source', Source::Swarm->value)
            ->whereNotNull('source_id')
            ->get()
            ->keyBy('source_id');

        $pulled = 0;

        try {
            foreach ($swarm->checkins($after) as $item) {
                $checkin = $stored->get((string) ($item['id'] ?? ''));

                // Likes and comments ride along with the check-in itself, so
                // there is nothing to skip for: reading it is the whole cost.
                if ($checkin === null) {
                    continue;
                }

                $pull($checkin, $item);
                $pulled++;
            }
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Done. Read responses for {$pulled} check-ins.");

        return self::SUCCESS;
    }

    /**
     * The unix timestamp to ask Foursquare for check-ins after. Normally
     * --days back, but the window stretches to the newest response we already
     * hold when a missed run has opened a longer gap, so a cron outage does
     * not strand a check-in's likes and comments permanently.
     */
    private function resolveAfterTimestamp(): int
    {
        $window = now()->subDays(max(0, (int) $this->option('days')));

        /** @var string|null $newest */
        $newest = SyndicatedResponse::query()->where('source', Source::Swarm->value)->max('occurred_at');

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
}
