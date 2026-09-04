<?php

namespace App\Services\Rovi;

use App\Services\ApiConnector;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\PendingRequest;

/** The Rovi API. Its base URL is configurable, so it is read per request. */
class RoviConnector extends ApiConnector
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

    public function boot(PendingRequest $pendingRequest): void
    {
        $key = config('services.rovi.key');

        if (is_string($key) && $key !== '') {
            $pendingRequest->authenticate(new TokenAuthenticator($key));
        }
    }
}
