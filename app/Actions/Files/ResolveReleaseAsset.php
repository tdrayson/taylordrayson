<?php

namespace App\Actions\Files;

use App\Data\FileData;
use App\Jobs\RefreshGitHubRelease;
use App\Support\ReleaseCache;
use Carbon\CarbonImmutable;

/**
 * The download card for a `file` block that points at a repo's latest release,
 * built from the cache alone.
 *
 * A stale entry is served as it stands and refreshed behind the request; a cold
 * one renders the filename and the download button without a version or size.
 * Neither waits on GitHub, and the link works in every case because it is the
 * moving /latest/download address rather than one naming a version.
 */
final class ResolveReleaseAsset
{
    public function __invoke(string $repo, string $asset): FileData
    {
        $release = ReleaseCache::get($repo);

        if (($release === null || ReleaseCache::isStale($release)) && ReleaseCache::claimRefresh($repo)) {
            RefreshGitHubRelease::dispatch($repo);
        }

        $file = $release['assets'][$asset] ?? null;

        return FileData::release(
            name: $asset,
            mime: $file['mime'] ?? null,
            size: $file['size'] ?? null,
            url: self::downloadUrl($repo, $asset),
            version: $release['version'] ?? null,
            releasedAt: $this->releaseDate($release['released_at'] ?? null),
        );
    }

    /** The release date as the card prints it, rather than as it is cached. */
    private function releaseDate(?string $publishedAt): ?string
    {
        return $publishedAt === null ? null : CarbonImmutable::parse($publishedAt)->format('j F Y');
    }

    /** GitHub's own permanent redirect to the newest release of an asset. */
    public static function downloadUrl(string $repo, string $asset): string
    {
        return 'https://github.com/'.$repo.'/releases/latest/download/'.rawurlencode($asset);
    }
}
