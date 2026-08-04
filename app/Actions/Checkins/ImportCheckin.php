<?php

namespace App\Actions\Checkins;

use App\Data\CheckinImport;
use App\Enums\Source;
use App\Models\Checkin;
use Throwable;

/**
 * Turn one Foursquare/Swarm API item into a stored check-in, with its photos.
 *
 * Shared by the one-time full history import and the recurring incremental
 * sync so the two can never drift into storing different shapes for the same
 * payload.
 */
class ImportCheckin
{
    /**
     * @param  array<string, mixed>  $item  A `checkins.items` entry from the Foursquare v2 API.
     */
    public function __invoke(array $item): CheckinImport
    {
        $venue = $item['venue'] ?? [];
        $location = $venue['location'] ?? [];

        $checkin = Checkin::updateOrCreate(
            ['source' => Source::Swarm->value, 'source_id' => $item['id']],
            [
                'occurred_at' => date('Y-m-d H:i:s', $item['createdAt']),
                'venue_name' => $venue['name'] ?? 'Unknown',
                'category' => $venue['categories'][0]['name'] ?? null,
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

        $created = $checkin->wasRecentlyCreated;
        [$photosAdded, $warnings] = $this->attachPhotos($checkin, $item['photos']['items'] ?? []);

        return new CheckinImport($checkin, $created, $photosAdded, $warnings);
    }

    /**
     * Attach a check-in's Swarm photos to its gallery, skipping when it already
     * has some so re-runs never duplicate. Photo URLs are built from the API's
     * prefix + size + suffix parts (https://location.foursquare.com/.../{size}).
     *
     * @param  array<int, array{prefix?: string, suffix?: string}>  $photos
     * @return array{0: int, 1: list<string>}
     */
    private function attachPhotos(Checkin $checkin, array $photos): array
    {
        if ($photos === [] || $checkin->getMedia('photos')->isNotEmpty()) {
            return [0, []];
        }

        $added = 0;
        $warnings = [];

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
                $warnings[] = "photo failed for {$checkin->venue_name}: {$exception->getMessage()}";
            }
        }

        return [$added, $warnings];
    }
}
