<?php

namespace App\Services\PocketCasts;

use App\Services\ApiConnector;
use App\Services\PocketCasts\Concerns\AuthenticatesWithToken;

/** The main Pocket Casts API host. */
class PocketCastsConnector extends ApiConnector
{
    use AuthenticatesWithToken;

    public function resolveBaseUrl(): string
    {
        return 'https://api.pocketcasts.com';
    }
}
