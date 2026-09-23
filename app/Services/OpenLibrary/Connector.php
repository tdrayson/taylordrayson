<?php

namespace App\Services\OpenLibrary;

use App\Services\ApiConnector;

/** The Open Library API. Keyless. */
class Connector extends ApiConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://openlibrary.org';
    }

    /** @return array<string, int> */
    protected function defaultConfig(): array
    {
        return ['connect_timeout' => 5, 'timeout' => 10];
    }
}
