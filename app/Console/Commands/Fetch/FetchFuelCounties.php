<?php

namespace App\Console\Commands\Fetch;

use App\Models\Fuel;
use App\Services\PetrolPrices\Client;
use App\Services\PetrolPrices\FuelStationResult;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('fuel:counties {--apply : Write the resolved counties to the database} {--radius=2 : Search radius in km around the stored forecourt coordinate}')]
#[Description('Fill in the county on fuel rows that have coordinates but no county')]
class FetchFuelCounties extends Command
{
    public function handle(Client $petrolPrices): int
    {
        $rows = Fuel::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNull('county')
            ->get();

        if ($rows->isEmpty()) {
            $this->components->info('No fuel rows are missing a county.');

            return self::SUCCESS;
        }

        // One lookup per forecourt rather than per row: the same station is
        // typically filled at many times over.
        $forecourts = $rows->groupBy(
            fn (Fuel $fuel): string => round((float) $fuel->latitude, 4).','.round((float) $fuel->longitude, 4),
        );

        $this->components->info("{$rows->count()} rows across {$forecourts->count()} forecourts.");

        $resolved = 0;
        $unmatched = 0;
        $countyless = 0;

        foreach ($forecourts as $group) {
            $station = $this->match($petrolPrices, $group->first());

            if ($station === null) {
                $this->components->warn("{$group->first()->station_name} - no matching forecourt");
                $unmatched += $group->count();

                continue;
            }

            if ($station->county === null) {
                $this->components->warn("{$group->first()->station_name} - forecourt has no county");
                $countyless += $group->count();

                continue;
            }

            $this->components->twoColumnDetail(
                (string) $group->first()->station_name,
                "{$station->county} ({$group->count()} ".str('row')->plural($group->count()).')',
            );

            if ($this->option('apply')) {
                Fuel::query()->whereIn('id', $group->pluck('id'))->update(['county' => $station->county]);
            }

            $resolved += $group->count();
        }

        $this->newLine();
        $this->components->info("Resolved {$resolved}, unmatched {$unmatched}, no county {$countyless}.");

        if (! $this->option('apply')) {
            $this->line('Dry run. Re-run with --apply to write these counties.');
        }

        return self::SUCCESS;
    }

    /**
     * The forecourt a stored row refers to.
     *
     * Matched on postcode or station name rather than proximity alone: the
     * nearest result to a forecourt's own coordinate is almost always itself,
     * but a stored postcode can be stale, and neighbouring forecourts are close
     * enough that distance alone would sometimes pick the wrong one.
     */
    private function match(Client $petrolPrices, Fuel $fuel): ?FuelStationResult
    {
        $stations = new Collection($petrolPrices->search(
            latitude: (float) $fuel->latitude,
            longitude: (float) $fuel->longitude,
            radiusKm: (float) $this->option('radius'),
        ));

        return $stations->first(fn (FuelStationResult $station): bool => $station->postcode === $fuel->postcode
            || mb_strtolower((string) $station->stationName) === mb_strtolower((string) $fuel->station_name));
    }
}
