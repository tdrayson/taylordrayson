<?php

namespace App\Services\Hardcover;

use App\Services\ApiConnector;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\PendingRequest;

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

    public function boot(PendingRequest $pendingRequest): void
    {
        $key = config('services.hardcover.key');

        if (! is_string($key) || $key === '') {
            return;
        }

        // Tokens sometimes arrive already prefixed with "Bearer "; strip so the
        // authenticator does not double up the scheme.
        $pendingRequest->authenticate(new TokenAuthenticator(
            str_starts_with($key, 'Bearer ') ? substr($key, 7) : $key,
        ));
    }
}
