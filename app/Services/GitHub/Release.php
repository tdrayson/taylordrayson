<?php

namespace App\Services\GitHub;

use Carbon\CarbonImmutable;

/** A repo's newest release, with its assets keyed by filename. */
final readonly class Release
{
    /** @param  array<string, ReleaseAsset>  $assets */
    public function __construct(
        public string $version,
        public ?CarbonImmutable $releasedAt,
        public array $assets,
    ) {}
}
