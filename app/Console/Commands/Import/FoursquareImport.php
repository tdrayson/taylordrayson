<?php

namespace App\Console\Commands\Import;

use App\Models\Checkin;
use App\Services\Foursquare;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('foursquare:import {--limit=0 : Max checkins to fetch (0 = all)}')]
#[Description('Import all check-in history from Foursquare/Swarm')]
class FoursquareImport extends Command
{
    public function handle(Foursquare $foursquare): int
    {
        $existingIds = Checkin::query()
            ->where('platform_type', 'swarm')
            ->whereNotNull('platform_id')
            ->pluck('platform_id')
            ->flip()
            ->all();

        $imported = 0;
        $skipped = 0;
        $limit = (int) $this->option('limit');

        try {
            foreach ($foursquare->checkins() as $item) {
                if (isset($existingIds[$item['id']])) {
                    $skipped++;

                    continue;
                }

                $venue = $item['venue'] ?? [];
                $location = $venue['location'] ?? [];
                $category = $venue['categories'][0]['name'] ?? null;

                Checkin::updateOrCreate(
                    ['platform_type' => 'swarm', 'platform_id' => $item['id']],
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
                        'is_mayor' => $item['isMayor'] ?? false,
                    ],
                );

                $imported++;
                $venueName = $venue['name'] ?? 'Unknown';
                $this->info("[{$imported}] {$venueName} — ".date('Y-m-d', $item['createdAt']));

                if ($limit > 0 && $imported >= $limit) {
                    break;
                }
            }
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Done. Imported {$imported} checkins, skipped {$skipped} existing.");

        return self::SUCCESS;
    }
}
