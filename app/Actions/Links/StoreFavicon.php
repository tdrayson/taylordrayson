<?php

namespace App\Actions\Links;

use App\Services\GoogleFavicons\Client;
use App\Support\Links;
use Illuminate\Support\Facades\File;

/**
 * Download and store one host's favicon. Shared by the save-time job and the
 * backfill command so there is a single place that decides where a favicon
 * lives and when it is worth re-fetching.
 */
class StoreFavicon
{
    public function __construct(private Client $favicons) {}

    /**
     * @return 'saved'|'unavailable'|'error'|'skipped' 'skipped' when one is already stored
     */
    public function __invoke(string $host, bool $force = false): string
    {
        $path = Links::faviconPath($host);

        if (! $force && File::exists($path)) {
            return 'skipped';
        }

        $result = $this->favicons->icon($host);

        if ($result['status'] === 'saved') {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $result['body']);
        }

        return $result['status'];
    }
}
