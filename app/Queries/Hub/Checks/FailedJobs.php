<?php

namespace App\Queries\Hub\Checks;

use App\Data\Hub\AttentionItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Queued work that gave up. Clears when the jobs are retried or forgotten.
 */
final class FailedJobs implements Check
{
    /**
     * @return list<AttentionItem>
     */
    public function items(): array
    {
        $jobs = DB::table('failed_jobs')->orderByDesc('failed_at')->get();

        if ($jobs->isEmpty()) {
            return [];
        }

        $newest = Carbon::parse($jobs->first()->failed_at);
        $names = $jobs->map(fn (object $job): string => class_basename(
            json_decode((string) $job->payload, true)['displayName'] ?? 'job'
        ))->unique()->take(2)->implode(', ');

        // One row for the lot: the decision is the same for all of them, and a
        // row each would bury everything else on a bad night.
        return [new AttentionItem(
            id: 'failed-jobs',
            kind: 'failure',
            icon: 'Alert02Icon',
            title: $jobs->count() === 1 ? 'A queued job failed' : "{$jobs->count()} queued jobs failed",
            detail: $names.', so whatever they were doing never happened.',
            body: null,
            age: $newest->diffForHumans(),
            href: '/hq',
            actions: [
                ['label' => 'Retry', 'action' => 'retry', 'variant' => 'secondary'],
                ['label' => 'Clear', 'action' => 'clear', 'variant' => 'ghost'],
            ],
        )];
    }
}
