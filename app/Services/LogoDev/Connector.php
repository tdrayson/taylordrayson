<?php

namespace App\Services\LogoDev;

use App\Services\ApiConnector;

/** The logo.dev image API. */
class Connector extends ApiConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://img.logo.dev';
    }
}
