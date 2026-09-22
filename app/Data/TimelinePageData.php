<?php

namespace App\Data;

use App\Models\TimelineEntry;
use Illuminate\Support\Collection;

/**
 * One page of the home timeline and the cursors either side of it. Cursors are
 * entry instants as `Y-m-d\TH:i:s`, null when nothing lies beyond.
 */
final readonly class TimelinePageData
{
    /**
     * @param  Collection<int, TimelineEntry>  $entries  Newest first.
     */
    public function __construct(
        public Collection $entries,
        public ?string $olderThan = null,
        public ?string $newerThan = null,
    ) {}
}
