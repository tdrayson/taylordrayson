<?php

namespace App\Services\LogoStream;

use App\Services\ApiConnector;

/**
 * LogoStream's aviation host, for route metadata. Shares the airline host's key
 * but wants it as a header rather than a query parameter.
 */
class AviationConnector extends ApiConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://aviation-api.logostream.dev';
    }

    /** @return array<string, mixed> */
    protected function defaultHeaders(): array
    {
        return ['x-api-key' => config('services.logostream.key')];
    }
}
