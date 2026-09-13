<?php

namespace App\Console\Commands\Import;

use App\Actions\Checkins\ImportCheckin;
use App\Services\Foursquare\Client;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('foursquare:import {--limit=0 : Max checkins to process (0 = all)} {--force : Re-check every checkin for missing photos}')]
#[Description('Import all check-in history from Foursquare/Swarm, with venue photos')]
class FoursquareImport extends Command
{
    /**
     * The one-time full backfill. Ongoing capture is `foursquare:sync`, which
     * asks the API only for check-ins newer than the last one stored rather
     * than paging the entire history on every run.
     */
    public function handle(Client $foursquare, ImportCheckin $importCheckin): int
    {
        $limit = (int) $this->option('limit');
        $imported = 0;
        $skipped = 0;
        $photosAdded = 0;
        $processed = 0;

        try {
            foreach ($foursquare->checkins() as $item) {
                $result = $importCheckin($item);

                $result->created ? $imported++ : $skipped++;
                $photosAdded += $result->photosAdded;

                foreach ($result->warnings as $warning) {
                    $this->warn("  {$warning}");
                }

                $processed++;
                $this->info(sprintf('[%d] %s - %s', $processed, $result->checkin->venue_name, date('Y-m-d', $item['createdAt'])));

                if ($limit > 0 && $processed >= $limit) {
                    break;
                }
            }
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Done. Imported {$imported} new, {$skipped} existing, {$photosAdded} photo(s) attached.");

        return self::SUCCESS;
    }
}
