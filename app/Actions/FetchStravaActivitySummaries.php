<?php

namespace App\Actions;

use App\Services\Strava;

/**
 * Page the athlete's full activity list once, keyed by Strava activity id. Paging
 * costs a request per 200 activities where fetching each one's UTC start
 * individually would cost one each against a 95-per-15-minute limit.
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
