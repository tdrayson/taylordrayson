<?php

namespace App\Services\PocketCasts\Concerns;

use App\Services\PocketCasts;
use App\Services\PocketCasts\LoginRequest;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;

/**
 * The bearer token and User-Agent both authenticated Pocket Casts hosts want,
 * plus re-authenticating once when a cached token has expired.
 *
 * The token is owned by {@see PocketCasts}, which logs in and
 * caches it; the connector only asks for the current one.
 */
trait AuthenticatesWithToken
{
    /** @var (callable(bool): string)|null */
    private $tokenResolver = null;

    /** @param  callable(bool): string  $resolver */
    public function resolvesTokenUsing(callable $resolver): static
    {
        $this->tokenResolver = $resolver;

        return $this;
    }

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json', 'User-Agent' => $this->userAgent()];
    }

    /** Pocket Casts refuses requests without one. */
    private function userAgent(): string
    {
        return 'taylordrayson.com (+'.config('site.social.github').')';
    }

    public function boot(PendingRequest $pendingRequest): void
    {
        // The login request is what produces the token, so authenticating it
        // here would call login() to satisfy login().
        if ($pendingRequest->getRequest() instanceof LoginRequest) {
            return;
        }

        if ($this->tokenResolver !== null) {
            $pendingRequest->authenticate(new TokenAuthenticator(($this->tokenResolver)(false)));
        }
    }

    /** A 401 means the cached token died early, so log in again and retry once. */
    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        if ($request instanceof LoginRequest) {
            return parent::handleRetry($exception, $request);
        }

        if ($exception instanceof RequestException && $exception->getResponse()->status() === 401 && $this->tokenResolver !== null) {
            ($this->tokenResolver)(true);

            return true;
        }

        return parent::handleRetry($exception, $request);
    }
}
