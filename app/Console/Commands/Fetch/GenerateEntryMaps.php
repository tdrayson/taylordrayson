<?php

namespace App\Console\Commands\Fetch;

use App\Actions\GenerateFlightMap;
use App\Actions\GenerateLocationMap;
use App\Actions\GenerateStaticMap;
use App\Exceptions\MapGenerationFailed;
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

        // Media is eager loaded because the skip check below reads it for every
        // row: lazily it cost one query per entry, ~1,500 per run for
        // activities, almost all of them to discover there was nothing to do.
        [$query, $generate] = match ($type) {
            'fuel' => [Fuel::query()->whereNotNull('latitude')->with('media'), fn ($m) => $pin($m, TypeColors::hex('fuel'))],
            'checkin' => [Checkin::query()->whereNotNull('latitude')->with('media'), fn ($m) => $pin($m, TypeColors::hex('checkin'))],
            'activity' => [Activity::query()->with('media'), fn ($m) => $route($m)],
            'flight' => [Flight::query()->with(['origin', 'destination', 'media']), fn ($m) => $arc($m)],
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
        $failures = [];

        $bar = $this->output->createProgressBar($models->count());
        $bar->start();

        foreach ($models as $model) {
            if (! $force && $model->getFirstMedia('map')) {
                $skipped++;
                $bar->advance();

                continue;
            }

            // One unreachable entry must not abandon the rest of the run: the
            // sweep exists to catch stragglers, so it collects failures and
            // reports them rather than stopping at the first.
            try {
                if ($generate($model)) {
                    $done++;
                }
            } catch (MapGenerationFailed $exception) {
                $failures[] = "#{$model->getKey()}: {$exception->getMessage()}";
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Generated {$done}, skipped {$skipped}.");

        if ($failures !== []) {
            $this->warn(count($failures).' failed and will be retried on the next run:');

            foreach (array_slice($failures, 0, 10) as $failure) {
                $this->line("  {$failure}");
            }
        }

        return self::SUCCESS;
    }
}
