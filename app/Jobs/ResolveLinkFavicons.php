<?php

namespace App\Jobs;

use App\Actions\Links\StoreFavicon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fetch the favicons for hosts an entry links to. Queued because saving an
 * entry should not wait on someone else's server, and because a host that is
 * slow or down must not be able to fail the save.
 */
class ResolveLinkFavicons implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $hosts
     */
    public function __construct(public array $hosts) {}

    public function handle(StoreFavicon $storeFavicon): void
    {
        foreach ($this->hosts as $host) {
            $storeFavicon($host);
        }
    }
}
