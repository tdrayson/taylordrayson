<?php

namespace App\Services\PocketCasts;

use App\Services\ApiConnector;
use App\Services\PocketCasts\Concerns\AuthenticatesWithToken;

/** The user and account host, which most of the API lives on. */
class UserApiConnector extends ApiConnector
{
    use AuthenticatesWithToken;

    public function resolveBaseUrl(): string
    {
        return 'https://api.pocketcasts.com';
    }
}
