<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The outbound request every third-party client makes, with a retry policy.
 *
 * Registered as the `Http::api()` macro. Without it a rate-limited or briefly
 * unavailable provider fails the whole command and the run is lost, which is a
 * temporary condition treated as a permanent one.
 */
class ApiHttp
{
    /** Attempts in total, not retries after the first. */
    private const TRIES = 3;

    /** Statuses worth waiting out. A 401 or 404 will not fix itself. */
    private const RETRYABLE = [429, 500, 502, 503, 504];

    /** Cap on an honoured Retry-After, so a long one cannot hold a cron run open. */
    private const MAX_WAIT_SECONDS = 30;

    public static function pending(): PendingRequest
    {
        return Http::retry(
            self::TRIES,
            self::backoff(...),
            self::shouldRetry(...),
            // Returning the failed response rather than throwing, so each
            // client's own error handling stays in charge once retries are out.
            throw: false,
        );
    }

    /**
     * How long to wait before the next attempt, in milliseconds.
     *
     * A provider that says Retry-After knows better than any guess, so that
     * wins; otherwise back off further on each attempt.
     */
    private static function backoff(int $attempt, Throwable $exception): int
    {
        $requested = $exception instanceof RequestException
            ? (int) $exception->response->header('Retry-After')
            : 0;

        return $requested > 0
            ? min($requested, self::MAX_WAIT_SECONDS) * 1000
            : $attempt * 500;
    }

    private static function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && in_array($exception->response->status(), self::RETRYABLE, true);
    }
}
