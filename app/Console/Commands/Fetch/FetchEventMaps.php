<?php

namespace App\Console\Commands\Fetch;

use App\Actions\GenerateLocationMap;
use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:maps {--force : Regenerate maps that already exist}')]
#[Description('Generate static location pin maps for events with coordinates')]
class FetchEventMaps extends Command
{
    public function handle(GenerateLocationMap $generate): int
    {
        $events = Event::query()->whereNotNull('latitude')->whereNotNull('longitude')->get();
        $done = 0;
        $skipped = 0;

        foreach ($events as $event) {
            if (! $this->option('force') && $event->getFirstMedia('map')) {
                $skipped++;

                continue;
            }

            if ($this->option('force')) {
                $event->clearMediaCollection('map');
            }

            if ($generate($event)) {
                $done++;
                $this->components->task("{$event->name}");
            }
        }

        $this->components->info("Generated {$done}, skipped {$skipped}.");

        return self::SUCCESS;
    }
}
