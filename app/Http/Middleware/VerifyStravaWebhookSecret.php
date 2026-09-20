<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyStravaWebhookSecret
{
    /**
     * Guard the callback with the unguessable segment of its own URL.
     *
     * Strava signs nothing and sends no credential on an event POST, so the
     * secret path is the only thing protecting the endpoint. A mismatch is a
     * 404 rather than a 401, so probing the URL never confirms it exists.
     * Fails closed: an unconfigured secret rejects everything.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.strava.webhook_secret');
        $provided = $request->route('secret');

        $valid = is_string($expected)
            && $expected !== ''
            && is_string($provided)
            && hash_equals($expected, $provided);

        abort_if(! $valid, 404);

        return $next($request);
    }
}
