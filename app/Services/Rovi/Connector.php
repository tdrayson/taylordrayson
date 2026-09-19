<?php

namespace App\Services\Rovi;

use App\Services\ApiConnector;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;

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

    protected function defaultAuth(): ?Authenticator
    {
        $key = $this->key();

        return $key === null ? null : new TokenAuthenticator($key);
    }

    protected function canAuthenticate(): bool
    {
        return $this->key() !== null;
    }

    /** The static Bearer token, or null when it is not configured. */
    private function key(): ?string
    {
        $key = config('services.rovi.key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
