<?php

namespace App\Services\Strava;

use App\Services\ApiConnector;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;

/**
 * The Strava API, owning the OAuth refresh-token flow.
 *
 * The token is applied in boot() rather than by each request, and cached until
 * just before it expires. An expired token still in cache comes back as a 401,
 * which {@see handleRetry} turns into a forced refresh and one more attempt.
 */
class Connector extends ApiConnector
{
    private const TOKEN_CACHE_KEY = 'strava_access_token';

    private const DEFAULT_EXPIRY_SECONDS = 3600;

    public function resolveBaseUrl(): string
    {
        return 'https://www.strava.com';
    }

    public function boot(PendingRequest $pendingRequest): void
    {
        // The token request authenticates itself with the client secret, and
        // authenticating it here would recurse.
        if ($pendingRequest->getRequest() instanceof TokenRequest) {
            return;
        }

        $token = $this->token();

        if ($token !== null) {
            $pendingRequest->authenticate(new TokenAuthenticator($token));
        }
    }

    /**
     * The cached access token, refreshing via the refresh-token grant when
     * missing or when forced after a 401.
     */
    public function token(bool $forceRefresh = false): ?string
    {
        if (! $forceRefresh) {
            $cached = cache(self::TOKEN_CACHE_KEY);

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $response = $this->send(new TokenRequest);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();
        $expiresIn = $data['expires_in'] ?? self::DEFAULT_EXPIRY_SECONDS;

        cache([self::TOKEN_CACHE_KEY => $data['access_token']], $expiresIn - 60);

        return $data['access_token'];
    }

    /**
     * A 401 means the cached token died early, so drop it and let the retry
     * re-authenticate through boot(). Everything else follows the base policy.
     */
    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        // Not for the token request itself: refreshing in response to its own
        // failure would call it again, and again.
        if ($request instanceof TokenRequest) {
            return parent::handleRetry($exception, $request);
        }

        if ($exception instanceof RequestException && $exception->getResponse()->status() === 401) {
            return $this->token(forceRefresh: true) !== null;
        }

        return parent::handleRetry($exception, $request);
    }
}
