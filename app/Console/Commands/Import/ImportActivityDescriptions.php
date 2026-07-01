<?php

namespace App\Console\Commands\Import;

use App\Models\Activity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('import:activity-descriptions {file : Path to the old-site activities export CSV} {--overwrite : Replace descriptions that are already set}')]
#[Description('Backfill activity descriptions from the old-site export, reading the Description embedded in its "Activity data" JSON and matching by Strava id')]
class ImportActivityDescriptions extends Command
{
    /**
     * The old-site export keeps each activity's real description inside the JSON
     * blob in its "Activity data" column (the "Content" column is a placeholder),
     * keyed by "Activity ID" which is the Strava id we store as platform_id.
     * Match on that and fill the description, skipping rows already set unless
     * --overwrite is given. The CSV seed is not written: run
     * `export:csv data/activities.csv activity` afterwards to refresh it.
     */
    public function handle(): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $descriptions = $this->descriptionsByStravaId($file);

        if ($descriptions === []) {
            $this->warn('No descriptions found in the export.');

            return self::SUCCESS;
        }

        $overwrite = (bool) $this->option('overwrite');
        $updated = 0;

        Activity::query()
            ->where('platform_type', 'strava')
            ->whereIn('platform_id', array_keys($descriptions))
            ->when(! $overwrite, fn ($query) => $query->whereNull('description'))
            ->each(function (Activity $activity) use ($descriptions, &$updated): void {
                $activity->update(['description' => $descriptions[$activity->platform_id]]);
                $updated++;
            });

        $this->info("Set descriptions on {$updated} activity(ies) from ".count($descriptions).' available in the export.');

        return self::SUCCESS;
    }

    /**
     * Map of Strava id => description for every export row whose "Activity data"
     * JSON carries a non-empty Description.
     *
     * @return array<string, string>
     */
    private function descriptionsByStravaId(string $file): array
    {
        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle, 0, ',', '"', '');

        if (! is_array($headers)) {
            fclose($handle);

            return [];
        }

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $map = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }

            $data = json_decode((string) array_combine($headers, $row)['Activity data'], true);

            if (! is_array($data)) {
                continue;
            }

            $stravaId = $data['Activity ID'] ?? null;
            $description = trim((string) ($data['Description'] ?? ''));

            if ($stravaId && $description !== '') {
                $map[(string) $stravaId] = $description;
            }
        }

        fclose($handle);

        return $map;
    }
}
