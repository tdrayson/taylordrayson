<?php

namespace App\Actions\Fuel;

use Carbon\CarbonImmutable;

/**
 * The GPS coordinate and capture time extracted from a receipt photo.
 */
readonly class ReceiptLocation
{
    public function __construct(
        public string $path,
        public CarbonImmutable $capturedAt,
        public float $latitude,
        public float $longitude,
    ) {}
}
