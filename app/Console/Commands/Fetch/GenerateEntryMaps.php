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

#[Signature('maps:generate {type : activity|flight|fuel|checkin} {--force : Regenerate maps that already exist}')]
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

        $done = 0;
        $skipped = 0;

        foreach ($query->get() as $model) {
            if (! $force && $model->getFirstMedia('map')) {
                $skipped++;

                continue;
            }

            if ($generate($model)) {
                $done++;
            }
        }

        $this->info("Generated {$done}, skipped {$skipped}.");

        return self::SUCCESS;
    }
}
