<?php

namespace App\Actions;

use App\Services\Strava;

/**
 * Page the athlete's full activity list once and keep only the fields the photo
 * commands need, keyed by Strava activity id.
 *
 * Both `strava:photos` and `strava:photo-locations` need each activity's UTC
 * start to place photos on the route. Fetching it per activity would cost one
 * request each against a 95-per-15-minute limit; paging the list costs roughly
 * one request per 200 activities.
 */
class FetchStravaActivitySummaries
{
    private const PER_PAGE = 200;

    public function __construct(private Strava $strava) {}

    /**
     * @return array<string, array{start_date: ?string, total_photo_count: int}>|null Null on a request failure.
     */
    public function __invoke(): ?array
    {
        $summaries = [];
        $page = 1;

        while (true) {
            $batch = $this->strava->activitiesPage($page, self::PER_PAGE);

            if ($batch === null) {
                return null;
            }

            if ($batch === []) {
                return $summaries;
            }

            foreach ($batch as $summary) {
                $summaries[(string) $summary['id']] = [
                    'start_date' => $summary['start_date'] ?? null,
                    'total_photo_count' => (int) ($summary['total_photo_count'] ?? 0),
                ];
            }

            $page++;
        }
    }
}
