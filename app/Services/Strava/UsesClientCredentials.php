<?php

namespace App\Services\Strava;

/**
 * A request that authenticates with the client id and secret rather than an
 * access token, so {@see Connector::boot()} must leave it alone: the token
 * endpoint (authenticating it would recurse) and the subscription endpoints,
 * which Strava rejects a bearer token on.
 */
interface UsesClientCredentials {}
