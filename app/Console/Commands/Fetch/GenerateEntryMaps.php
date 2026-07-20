<?php

namespace App\Console\Commands\Fetch;

use App\Actions\GenerateFlightMap;
use App\Actions\GenerateLocationMap;
use App\Actions\GenerateStaticMap;
use App\Models\Activity;
use App\Models\Checkin;
use App\Models\Flight;
use App\Models\Fuel;
use App\Support\TypeColors;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('maps:generate {type : activity|flight|fuel|checkin} {--limit=0 : Max entries to process (0 = all)} {--force : Regenerate maps that already exist}')]
#[Description('Generate and store static timeline maps for a located entry type')]
class GenerateEntryMaps extends Command
{
    public function handle(GenerateLocationMap $pin, GenerateStaticMap $route, GenerateFlightMap $arc): int
    {
        $type = $this->argument('type');
        $force = (bool) $this->option('force');

        [$query, $generate] = match ($type) {
            'fuel' => [Fuel::query()->whereNotNull('latitude'), fn ($m) => $pin($m, TypeColors::hex('fuel'))],
            'checkin' => [Checkin::query()->whereNotNull('latitude'), fn ($m) => $pin($m, TypeColors::hex('checkin'))],
            'activity' => [Activity::query(), fn ($m) => $route($m)],
            'flight' => [Flight::query()->with(['origin', 'destination']), fn ($m) => $arc($m)],
            default => [null, null],
        };

        if ($query === null) {
            $this->error("Unknown type: {$type}. Use activity, flight, fuel, or checkin.");

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $models = $query->get();

        if ($models->isEmpty()) {
            $this->info("No {$type} entries to map.");

            return self::SUCCESS;
        }

        // Each entry costs one or two synchronous Mapbox fetches, so a full
        // backfill runs for many minutes. Show a progress bar so the command is
        // visibly working rather than appearing to hang with no output.
        $this->info("Generating maps for {$models->count()} {$type} entries...");

        $done = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($models->count());
        $bar->start();

        foreach ($models as $model) {
            if (! $force && $model->getFirstMedia('map')) {
                $skipped++;
            } elseif ($generate($model)) {
                $done++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Generated {$done}, skipped {$skipped}.");

        return self::SUCCESS;
    }
}
