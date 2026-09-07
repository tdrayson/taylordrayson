<?php

namespace App\Actions\Checkins;

use App\Data\CheckinImport;
use App\Enums\Source;
use App\Models\Checkin;
use App\Support\VenueTimezone;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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
    public function __construct(private readonly VenueTimezone $timezones) {}

    /**
     * @param  array<string, mixed>  $item  A `checkins.items` entry from the Foursquare v2 API.
     */
    public function __invoke(array $item): CheckinImport
    {
        $venue = $item['venue'] ?? [];
        $location = $venue['location'] ?? [];

        // Where the venue is, not where the server is. `createdAt` is a UTC
        // timestamp, so rendering it with date() would give the server's clock
        // rather than the one on the wall at the time.
        $timezone = $this->timezones->forCoordinate($location['lat'] ?? null, $location['lng'] ?? null);

        $checkin = Checkin::updateOrCreate(
            ['source' => Source::Swarm->value, 'source_id' => $item['id']],
            [
                'occurred_at' => $this->timezones->localWallClock($item['createdAt'], $timezone),
                'timezone' => $timezone,
                'venue_name' => $venue['name'] ?? 'Unknown',
                'category' => $venue['categories'][0]['name'] ?? null,
                'address' => $location['address'] ?? null,
                'postcode' => $location['postalCode'] ?? null,
                'city' => $location['city'] ?? null,
                'county' => $location['state'] ?? null,
                'country' => $location['country'] ?? null,
                'latitude' => $location['lat'] ?? null,
                'longitude' => $location['lng'] ?? null,
                'description' => $item['shout'] ?? null,
                'event_name' => $item['event']['name'] ?? null,
            ],
        );

        $created = $checkin->wasRecentlyCreated;
        [$photosAdded, $warnings] = $this->attachPhotos($checkin, $item['photos']['items'] ?? []);

        return new CheckinImport($checkin, $created, $photosAdded, $warnings);
    }

    /**
     * Attach a check-in's Swarm photos to its gallery, one photo at a time.
     *
     * Skipped per photo rather than per check-in: the old guard bailed as soon
     * as a check-in had any photo at all, so a second one added in Swarm days
     * later could never arrive. Identity is the filename Foursquare gives each
     * photo, which survives the conversion to webp that the stored name shows.
     *
     * Photo URLs are built from the API's prefix + size + suffix parts
     * (https://location.foursquare.com/.../{size}).
     *
     * @param  array<int, array{prefix?: string, suffix?: string}>  $photos
     * @return array{0: int, 1: list<string>}
     */
    private function attachPhotos(Checkin $checkin, array $photos): array
    {
        if ($photos === []) {
            return [0, []];
        }

        $stored = $checkin->getMedia('photos')
            ->map(fn (Media $media): string => self::stemOf($media->file_name))
            ->all();

        $added = 0;
        $warnings = [];

        foreach ($photos as $photo) {
            $prefix = $photo['prefix'] ?? null;
            $suffix = $photo['suffix'] ?? null;

            if (! is_string($prefix) || ! is_string($suffix)) {
                continue;
            }

            if (in_array(self::stemOf($suffix), $stored, true)) {
                continue;
            }

            try {
                $checkin->addMediaFromUrl($prefix.'original'.$suffix)->toMediaCollection('photos');
                $stored[] = self::stemOf($suffix);
                $added++;
            } catch (Throwable $exception) {
                $warnings[] = "photo failed for {$checkin->venue_name}: {$exception->getMessage()}";
            }
        }

        return [$added, $warnings];
    }

    /** A photo's name without its path or extension, which the format change loses. */
    private static function stemOf(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }
}
