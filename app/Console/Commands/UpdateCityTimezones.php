<?php

namespace App\Console\Commands;

use App\Support\CityTimezones;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;
use ZipArchive;

/**
 * Rebuild the bundled city/timezone table from GeoNames.
 *
 * Run by hand and the result committed, never on deploy: fetching it at boot
 * would make geonames.org a deployment dependency, which is the failure this
 * whole approach exists to avoid. Cities do not move, so once a year is ample.
 */
#[Signature('timezones:update-cities {--dry-run : Report what would change without writing}')]
#[Description('Rebuild the bundled city timezone table from the GeoNames export')]
class UpdateCityTimezones extends Command
{
    private const SOURCE = 'https://download.geonames.org/export/dump/cities15000.zip';

    /** Tab-separated columns in the GeoNames export, which has no header row. */
    private const LATITUDE = 4;

    private const LONGITUDE = 5;

    private const TIMEZONE = 17;

    /** Below this the download is a courtesy page or a truncated file. */
    private const MIN_ROWS = 20000;

    public function handle(CityTimezones $cities): int
    {
        $rows = $this->download();

        if ($rows === null) {
            return self::FAILURE;
        }

        if (count($rows) < self::MIN_ROWS) {
            $this->components->error('Only '.count($rows).' cities parsed; refusing to overwrite.');

            return self::FAILURE;
        }

        sort($rows);
        $existing = is_file($cities->path()) ? substr_count((string) file_get_contents($cities->path()), "\n") : 0;

        $this->components->info(count($rows).' cities parsed, '.$existing.' currently bundled.');

        if ($this->option('dry-run')) {
            $this->components->warn('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        file_put_contents($cities->path(), implode("\n", $rows)."\n");
        $this->components->info('Wrote '.$cities->path().'. Commit it.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>|null
     */
    private function download(): ?array
    {
        $archive = tempnam(sys_get_temp_dir(), 'geonames').'.zip';

        try {
            $response = Http::api()->timeout(120)->get(self::SOURCE);

            if (! $response->successful()) {
                $this->components->error('GeoNames returned '.$response->status().'.');

                return null;
            }

            file_put_contents($archive, $response->body());

            return $this->rowsFrom($archive);
        } catch (Throwable $exception) {
            $this->components->error('Download failed: '.$exception->getMessage());

            return null;
        } finally {
            @unlink($archive);
        }
    }

    /**
     * @return list<string>|null
     */
    private function rowsFrom(string $archive): ?array
    {
        $zip = new ZipArchive;

        if ($zip->open($archive) !== true) {
            $this->components->error('Could not open the GeoNames archive.');

            return null;
        }

        $contents = $zip->getFromName('cities15000.txt');
        $zip->close();

        if ($contents === false) {
            $this->components->error('The archive holds no cities15000.txt.');

            return null;
        }

        $rows = [];

        foreach (explode("\n", $contents) as $line) {
            $city = explode("\t", $line);

            if (count($city) <= self::TIMEZONE) {
                continue;
            }

            [$latitude, $longitude, $timezone] = [$city[self::LATITUDE], $city[self::LONGITUDE], trim($city[self::TIMEZONE])];

            if ($latitude === '' || $longitude === '' || $timezone === '') {
                continue;
            }

            $rows[sprintf('%.4f,%.4f,%s', (float) $latitude, (float) $longitude, $timezone)] = true;
        }

        return array_keys($rows);
    }
}
