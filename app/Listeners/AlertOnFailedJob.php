<?php

namespace App\Listeners;

use App\Support\FailureAlert;
use Illuminate\Queue\Events\JobFailed;

/**
 * A queued job that has exhausted its retries and landed in `failed_jobs`,
 * where one sat unnoticed for days before this existed.
 */
class AlertOnFailedJob
{
    public function __construct(private readonly FailureAlert $alerts) {}

    public function handle(JobFailed $event): void
    {
        $job = $event->job->resolveName();

        $this->alerts->report(
            'job:'.$job,
            'Queued job failed',
            $job.' failed: '.$event->exception->getMessage(),
        );
    }
}
