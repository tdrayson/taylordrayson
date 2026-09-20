<?php

namespace App\Data;

use App\Actions\Strava\StoreStravaActivity;
use App\Models\Activity;

/** What one pass of {@see StoreStravaActivity} did to a row. */
final readonly class StoredStravaActivity
{
    /**
     * @param  bool  $created  A row that did not exist in any form before this.
     * @param  bool  $adopted  An unclaimed Setgraph row taken over rather than duplicated.
     * @param  array<int, string>  $changed  The columns that actually moved.
     */
    public function __construct(
        public Activity $activity,
        public bool $created,
        public bool $adopted = false,
        public array $changed = [],
        public int $photos = 0,
    ) {}
}
