<?php

namespace App\Actions;

use App\Console\Commands\Fetch\FetchLinkFavicons;
use App\Support\Links;

/**
 * Map the external hosts in a document to their stored favicons. Read-only:
 * anything not already downloaded is simply absent, and the chip falls back to
 * a globe rather than the render waiting on a request.
 *
 * @see FetchLinkFavicons for what fills the directory.
 */
class BuildLinkFavicons
{
    /**
     * @param  array<int, array<string, mixed>>|null  $blocks
     * @return array<string, string>
     */
    public function __invoke(?array $blocks): array
    {
        $favicons = [];

        foreach (Links::hostsIn($blocks) as $host) {
            $url = Links::faviconUrl($host);

            if ($url !== null) {
                $favicons[$host] = $url;
            }
        }

        return $favicons;
    }
}
