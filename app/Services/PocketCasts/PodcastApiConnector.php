<?php

namespace App\Services\PocketCasts;

use App\Services\ApiConnector;
use App\Services\PocketCasts\Concerns\AuthenticatesWithToken;

/** The podcast-metadata host, which takes the same token. */
class PodcastApiConnector extends ApiConnector
{
    use AuthenticatesWithToken;

    public function resolveBaseUrl(): string
    {
        return 'https://podcast-api.pocketcasts.com';
    }
}
