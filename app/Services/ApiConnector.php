<?php

namespace App\Services;

use App\Support\ApiHttp;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Connector;
use Saloon\Http\Request;

/**
 * Base connector for every third-party API, carrying the retry policy that
 * {@see ApiHttp} applies to the hand-rolled clients.
 *
 * Without it a rate-limited or briefly unavailable provider fails the whole
 * command and the run is lost, which is a temporary condition treated as a
 * permanent one.
 */
abstract class ApiConnector extends Connector
{
    /** Attempts in total, not retries after the first. */
    public ?int $tries = 3;

    /** Doubled on each attempt by {@see $useExponentialBackoff}. */
    public ?int $retryInterval = 500;

    public ?bool $useExponentialBackoff = true;

    /**
     * Return the failed response rather than throwing, so each client's own
     * error handling stays in charge once retries are out.
     */
    public ?bool $throwOnMaxTries = false;

    /** Statuses worth waiting out. A 401 or 404 will not fix itself. */
    private const RETRYABLE = [429, 500, 502, 503, 504];

    /**
     * A connection failure is always worth another go; a response is only worth
     * retrying on the statuses above.
     */
    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        if ($exception instanceof FatalRequestException) {
            return true;
        }

        return in_array($exception->getResponse()->status(), self::RETRYABLE, true);
    }

    /**
     * Whether the connection has what it needs to authenticate. Connectors
     * whose credential is acquired at runtime override this.
     */
    protected function canAuthenticate(): bool
    {
        return true;
    }

    /**
     * Send a request and decode it, treating any failure as no data.
     *
     * @param  string|Request  $target  A path to GET, or a request to send.
     * @param  array<string, mixed>  $query  Applied to a path only.
     * @return array<array-key, mixed>|null
     */
    public function json(string|Request $target, array $query = []): ?array
    {
        // An unauthenticated call to a provider that wants credentials is just
        // a slower way of getting null.
        if (! $this->canAuthenticate()) {
            return null;
        }

        $response = $this->send(is_string($target) ? new GetRequest($target, $query) : $target);

        return $response->failed() ? null : $response->json();
    }
}
