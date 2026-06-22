<?php

namespace App\Console\Commands;

use App\Models\Checkin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('foursquare:import {--limit=0 : Max checkins to fetch (0 = all)}')]
#[Description('Import all check-in history from Foursquare/Swarm')]
class FoursquareImport extends Command
{
    private const PER_PAGE = 250;

    public function handle(): int
    {
        $token = config('services.foursquare.access_token');

        if (! $token) {
            $this->error('FOURSQUARE_ACCESS_TOKEN is not set.');

            return self::FAILURE;
        }

        $existingIds = Checkin::query()
            ->where('platform_type', 'swarm')
            ->whereNotNull('platform_id')
            ->pluck('platform_id')
            ->flip()
            ->all();

        $offset = 0;
        $imported = 0;
        $skipped = 0;
        $limit = (int) $this->option('limit');

        while (true) {
            $response = Http::get('https://api.foursquare.com/v2/users/self/checkins', [
                'oauth_token' => $token,
                'v' => '20240109',
                'limit' => self::PER_PAGE,
                'offset' => $offset,
                'sort' => 'newestfirst',
            ]);

            if ($response->failed()) {
                $this->error("API request failed: {$response->status()} — {$response->body()}");

                return self::FAILURE;
            }

            $items = $response->json('response.checkins.items');

            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
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
                    break 2;
                }
            }

            $offset += self::PER_PAGE;
        }

        $this->info("Done. Imported {$imported} checkins, skipped {$skipped} existing.");

        return self::SUCCESS;
    }
}
