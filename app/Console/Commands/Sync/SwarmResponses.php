<?php

namespace App\Console\Commands\Sync;

use App\Actions\Syndicated\PullSwarmResponses;
use App\Enums\Source;
use App\Models\Checkin;
use App\Services\Foursquare\Client;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('swarm:responses {--days=7 : How many days back to check} {--all : Every check-in ever, repairing anything stale}')]
#[Description('Pull the likes and comments left on Swarm onto the check-ins they belong to')]
class SwarmResponses extends Command
{
    public function handle(Client $swarm, PullSwarmResponses $pull): int
    {
        $all = (bool) $this->option('all');
        $after = $all ? null : now()->subDays((int) $this->option('days'))->timestamp;

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
}
