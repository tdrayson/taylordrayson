<?php

namespace App\Jobs;

use App\Services\GitHub\Client;
use App\Support\ReleaseCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-read a repo's latest release into the cache. Queued because a page render
 * asks for this while serving the value it already has, so the request must not
 * be on the critical path.
 */
class RefreshGitHubRelease implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $repo) {}

    public function handle(Client $github): void
    {
        $release = $github->latestRelease($this->repo);

        // A failed lookup leaves the existing entry alone: stale beats empty,
        // and the next render asks again once the refresh window is up.
        if ($release !== null) {
            ReleaseCache::put($this->repo, $release);
        }
    }
}
