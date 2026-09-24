<?php

namespace App\Data;

/**
 * The outcome of a push-subscription call.
 *
 * Strava's failure body is the useful part here, since a refused create says
 * exactly why ("GET to callback URL does not return 200"), so this carries the
 * message rather than collapsing a failure to null.
 */
final readonly class StravaSubscriptionResult
{
    public function __construct(
        public bool $ok,
        public ?int $id = null,
        public ?string $callbackUrl = null,
        public ?string $error = null,
    ) {}
}
