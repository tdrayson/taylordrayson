<?php

namespace App\Console\Commands\Import;

use App\Enums\Source;
use App\Models\Checkin;
use App\Services\Foursquare;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

#[Signature('foursquare:import {--limit=0 : Max checkins to process (0 = all)} {--force : Re-check every checkin for missing photos}')]
#[Description('Import all check-in history from Foursquare/Swarm, with venue photos')]
class FoursquareImport extends Command
{
    public function handle(Foursquare $foursquare): int
    {
        $limit = (int) $this->option('limit');
        $imported = 0;
        $skipped = 0;
        $photosAdded = 0;
        $processed = 0;

        try {
            foreach ($foursquare->checkins() as $item) {
                $venue = $item['venue'] ?? [];
                $location = $venue['location'] ?? [];
                $category = $venue['categories'][0]['name'] ?? null;

                $checkin = Checkin::updateOrCreate(
                    ['source' => Source::Swarm->value, 'source_id' => $item['id']],
                    [
                        'occurred_at' => date('Y-m-d H:i:s', $item['createdAt']),
                        'venue_name' => $venue['name'] ?? 'Unknown',
                        'category' => $category,
                        'address' => $location['address'] ?? null,
                        'city' => $location['city'] ?? null,
                        'county' => $location['state'] ?? null,
                        'country' => $location['country'] ?? null,
                        'latitude' => $location['lat'] ?? null,
                        'longitude' => $location['lng'] ?? null,
                        'description' => $item['shout'] ?? null,
                        'event_name' => $item['event']['name'] ?? null,
                        'is_mayor' => $item['isMayor'] ?? false,
                    ],
                );

                $checkin->wasRecentlyCreated ? $imported++ : $skipped++;
                $photosAdded += $this->attachPhotos($checkin, $item['photos']['items'] ?? []);

                $processed++;
                $this->info(sprintf('[%d] %s - %s', $processed, $venue['name'] ?? 'Unknown', date('Y-m-d', $item['createdAt'])));

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

    /**
     * Attach a check-in's Swarm photos to its gallery, skipping when it already
     * has some so re-runs never duplicate. Photo URLs are built from the API's
     * prefix + size + suffix parts (https://location.foursquare.com/.../{size}).
     *
     * @param  array<int, array{prefix?: string, suffix?: string}>  $photos
     */
    private function attachPhotos(Checkin $checkin, array $photos): int
    {
        if ($photos === [] || $checkin->getMedia('photos')->isNotEmpty()) {
            return 0;
        }

        $added = 0;

        foreach ($photos as $photo) {
            $prefix = $photo['prefix'] ?? null;
            $suffix = $photo['suffix'] ?? null;

            if (! is_string($prefix) || ! is_string($suffix)) {
                continue;
            }

            try {
                $checkin->addMediaFromUrl($prefix.'original'.$suffix)->toMediaCollection('photos');
                $added++;
            } catch (Throwable $exception) {
                $this->warn("  photo failed for {$checkin->venue_name}: {$exception->getMessage()}");
            }
        }

        return $added;
    }
}
