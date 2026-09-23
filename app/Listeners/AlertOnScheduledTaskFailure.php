<?php

namespace App\Listeners;

use App\Support\FailureAlert;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;

/**
 * A scheduled command that exits non-zero, which until now only ever reached
 * the log. Most of what this site does runs on the scheduler, so a silently
 * broken sync is the likeliest failure it has. A background run, which is how
 * HQ's Sync now runs one, only reports its exit code once it has finished.
 */
class AlertOnScheduledTaskFailure
{
    public function __construct(private readonly FailureAlert $alerts) {}

    public function handle(ScheduledTaskFailed|ScheduledBackgroundTaskFinished $event): void
    {
        if ($event instanceof ScheduledBackgroundTaskFinished && $event->task->exitCode === 0) {
            return;
        }

        $command = $this->name($event->task->command ?? '');

        $reason = $event instanceof ScheduledTaskFailed
            ? $event->exception->getMessage()
            : 'exit code '.$event->task->exitCode;

        $this->alerts->report(
            'schedule:'.$command,
            'Scheduled command failed',
            $command.' exited non-zero: '.$reason,
        );
    }

    /**
     * The artisan command out of the scheduler's full shell string, which reads
     * `'/usr/bin/php8.4' 'artisan' this-week-with:sync > '/dev/null' 2>&1`.
     */
    private function name(string $command): string
    {
        $withoutRedirect = trim(preg_replace('/\s*>.*$/', '', $command) ?? $command);
        $afterArtisan = preg_replace("/^.*'artisan'\s*/", '', $withoutRedirect);

        return trim($afterArtisan ?? '') ?: 'scheduled task';
    }
}
