<?php

namespace App\Services\GitHub;

use Carbon\CarbonImmutable;

/**
 * Client for the GitHub REST API, which the site reads only for the version and
 * asset sizes behind a release download.
 *
 * Needs no credentials; an optional token (config('services.github.token'))
 * raises the anonymous 60 requests an hour.
 */
class Client
{
    public function __construct(private readonly Connector $connector) {}

    /**
     * The newest published release of `$repo`, in owner/name form.
     *
     * A failed request, a repo with no release yet and an unreadable body all
     * yield null rather than throwing, because the caller has a stale or empty
     * cache to fall back on and a download link that works regardless.
     */
    public function latestRelease(string $repo): ?Release
    {
        $response = $this->connector->send(new LatestReleaseRequest($repo));

        if (! $response->successful()) {
            return null;
        }

        $version = $response->json('tag_name');

        if (! is_string($version) || $version === '') {
            return null;
        }

        $publishedAt = $response->json('published_at');

        return new Release(
            version: $version,
            releasedAt: is_string($publishedAt) ? CarbonImmutable::parse($publishedAt) : null,
            assets: $this->assets($response->json('assets') ?? []),
        );
    }

    /**
     * Map the release's asset list to value objects keyed by filename, which is
     * how a document refers to one.
     *
     * @param  array<int, array<string, mixed>>  $assets
     * @return array<string, ReleaseAsset>
     */
    private function assets(array $assets): array
    {
        $mapped = [];

        foreach ($assets as $asset) {
            $name = $asset['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $mime = $asset['content_type'] ?? null;

            $mapped[$name] = new ReleaseAsset(
                name: $name,
                size: (int) ($asset['size'] ?? 0),
                mime: is_string($mime) && $mime !== '' ? $mime : null,
            );
        }

        return $mapped;
    }
}
