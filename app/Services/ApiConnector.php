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
}
