<?php

namespace App\Services\Hardcover;

use App\Services\ApiConnector;

/** The Hardcover GraphQL API. */
class HardcoverConnector extends ApiConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://api.hardcover.app/v1';
    }

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    /** @return array<string, int> */
    protected function defaultConfig(): array
    {
        return ['connect_timeout' => 10, 'timeout' => 30];
    }
}
