<?php

namespace App\Data;

use App\Models\Checkin;

/**
 * The outcome of importing one Foursquare/Swarm check-in. Carries its warnings
 * rather than printing them, since each caller renders them differently.
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
