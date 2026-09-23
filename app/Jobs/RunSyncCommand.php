<?php

namespace App\Jobs;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Run a sync now rather than at its next slot, as its own scheduled event, so a
 * manual run and a scheduled one share the one overlap lock.
 */
class RunSyncCommand implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $command  the Artisan command as scheduled, e.g. `strava:sync --days=2 --refresh`
     */
    public function __construct(public string $command) {}

    public function handle(Kernel $console, Container $container): void
    {
        // The events are defined in routes/console.php, which only a console bootstrap loads.
        $console->bootstrap();

        $event = collect($container->make(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_ends_with($event->command ?? '', ' '.$this->command));

        if ($event === null) {
            throw new RuntimeException("Nothing on the schedule runs {$this->command}.");
        }

        // In the background, so a long sync never hits the worker's timeout;
        // schedule:finish releases the lock when it exits.
        (clone $event)->runInBackground()->run($container);
    }
}
