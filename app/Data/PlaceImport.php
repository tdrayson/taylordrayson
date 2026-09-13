<?php

namespace App\Data;

use App\Models\Place;

/**
 * The outcome of importing one Foursquare/Swarm check-in. Carries its warnings
 * rather than printing them, since each caller renders them differently.
 */
final readonly class PlaceImport
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public Place $place,
        public bool $created,
        public int $photosAdded,
        public array $warnings = [],
    ) {}
}
