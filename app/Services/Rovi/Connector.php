<?php

namespace App\Services\Rovi;

use App\Services\ApiConnector;

/** The Rovi API. Its base URL is configurable, so it is read per request. */
class Connector extends ApiConnector
{
    public function resolveBaseUrl(): string
    {
        return rtrim((string) config('services.rovi.base_url'), '/');
    }

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }
}
