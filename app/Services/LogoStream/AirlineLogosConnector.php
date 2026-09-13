<?php

namespace App\Services\LogoStream;

use App\Services\ApiConnector;

/** LogoStream's airline-logo host, which takes its key as a query parameter. */
class AirlineLogosConnector extends ApiConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://airlines-api.logostream.dev';
    }
}
