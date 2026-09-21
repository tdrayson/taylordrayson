<?php

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;

it('schedules the fuel brand and airline logo fetches', function (string $command) {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn (ScheduledEvent $event): string => $event->command);

    expect($commands->contains(fn (string $scheduled): bool => str_contains($scheduled, $command)))->toBeTrue();
})->with(['fuel:brand-logos', 'airlines:logos']);
