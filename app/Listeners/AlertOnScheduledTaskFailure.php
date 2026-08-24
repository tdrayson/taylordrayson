<?php

namespace App\Listeners;

use App\Support\FailureAlert;
use Illuminate\Console\Events\ScheduledTaskFailed;

/**
 * A scheduled command that exits non-zero, which until now only ever reached
 * the log. Most of what this site does runs on the scheduler, so a silently
 * broken sync is the likeliest failure it has.
 */
class AlertOnScheduledTaskFailure
{
    public function __construct(private readonly FailureAlert $alerts) {}

    public function handle(ScheduledTaskFailed $event): void
    {
        $command = $this->name($event->task->command ?? '');

        $this->alerts->report(
            'schedule:'.$command,
            'Scheduled command failed',
            $command.' exited non-zero: '.$event->exception->getMessage(),
        );
    }

    /**
     * The artisan command out of the scheduler's full shell string, which reads
     * `'/usr/bin/php8.4' 'artisan' podcast:sync > '/dev/null' 2>&1`.
     */
    private function name(string $command): string
    {
        $withoutRedirect = trim(preg_replace('/\s*>.*$/', '', $command) ?? $command);
        $afterArtisan = preg_replace("/^.*'artisan'\s*/", '', $withoutRedirect);

        return trim($afterArtisan ?? '') ?: 'scheduled task';
    }
}
