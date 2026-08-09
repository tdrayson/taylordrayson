<?php

namespace App\Console\Commands\Fetch;

use App\Actions\GenerateLocationMap;
use App\Exceptions\MapGenerationFailed;
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
        $events = Event::query()->whereNotNull('latitude')->whereNotNull('longitude')->with('media')->get();
        $done = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($events as $event) {
            if (! $this->option('force') && $event->getFirstMedia('map')) {
                $skipped++;

                continue;
            }

            // Collected rather than fatal: one unreachable event should not
            // abandon the rest, and a failed event stays eligible for the
            // next run because no map was stored for it.
            try {
                if ($generate($event)) {
                    $done++;
                    $this->components->task("{$event->name}");
                }
            } catch (MapGenerationFailed $exception) {
                $failed++;
                $this->components->warn("{$event->name}: {$exception->getMessage()}");
            }
        }

        $this->components->info("Generated {$done}, skipped {$skipped}".($failed > 0 ? ", failed {$failed}" : '').'.');

        return self::SUCCESS;
    }
}
