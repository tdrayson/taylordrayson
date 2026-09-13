<?php

namespace App\Support;

use App\Services\GitHub\Release;
use App\Services\GitHub\ReleaseAsset;
use Illuminate\Support\Facades\Cache;

/**
 * The latest release of each repo, cached per repo so one lookup covers every
 * asset a document links to.
 *
 * Entries outlive their freshness on purpose: a page renders from whatever is
 * stored and asks for a refresh in the background, so nothing waits on GitHub
 * and an entry hours out of date is a wrong version number, never a broken
 * download.
 */
final class ReleaseCache
{
    /** How long an entry reads as current before a refresh is asked for. */
    private const FRESH_FOR = 3600;

    /** Long enough that a repo nobody has touched in a month still has a value. */
    private const KEEP_FOR = 2592000;

    /** One refresh per repo per window, so a busy page cannot flood the queue. */
    private const REFRESH_EVERY = 300;

    /**
     * @return array{version: string, released_at: string|null, fetched_at: int, assets: array<string, array{size: int, mime: string|null}>}|null
     */
    public static function get(string $repo): ?array
    {
        return Cache::get(self::key($repo));
    }

    public static function put(string $repo, Release $release): void
    {
        Cache::put(self::key($repo), [
            'version' => $release->version,
            'released_at' => $release->releasedAt?->toIso8601String(),
            'fetched_at' => now()->timestamp,
            'assets' => array_map(
                fn (ReleaseAsset $asset): array => ['size' => $asset->size, 'mime' => $asset->mime],
                $release->assets,
            ),
        ], self::KEEP_FOR);
    }

    /** @param  array{fetched_at: int}  $entry */
    public static function isStale(array $entry): bool
    {
        return now()->timestamp > $entry['fetched_at'] + self::FRESH_FOR;
    }

    /**
     * True for the first caller to ask about a repo in the current window, so
     * only that one dispatches the refresh.
     */
    public static function claimRefresh(string $repo): bool
    {
        return Cache::add(self::key($repo).':refreshing', true, self::REFRESH_EVERY);
    }

    private static function key(string $repo): string
    {
        return 'github:release:'.strtolower($repo);
    }
}
