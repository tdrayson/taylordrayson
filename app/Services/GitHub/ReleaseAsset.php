<?php

namespace App\Services\GitHub;

/**
 * One downloadable file attached to a release. Carries no URL: a release asset
 * URL names its own version, and the site links to the moving /latest/download
 * address instead so the link survives the next release.
 */
final readonly class ReleaseAsset
{
    public function __construct(
        public string $name,
        public int $size,
        public ?string $mime,
    ) {}
}
