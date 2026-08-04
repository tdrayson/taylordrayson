<?php

namespace App\Data;

use App\Models\Checkin;

/**
 * The outcome of importing one Foursquare/Swarm check-in.
 *
 * Carries the warnings rather than printing them so the action stays free of
 * console concerns: the full importer and the incremental sync render them
 * differently, and a queued caller would render them not at all.
 */
final readonly class CheckinImport
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public Checkin $checkin,
        public bool $created,
        public int $photosAdded,
        public array $warnings = [],
    ) {}
}
