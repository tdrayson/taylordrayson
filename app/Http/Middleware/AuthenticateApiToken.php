<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * Authenticate against the single static API token. Fails closed:
     * an unconfigured token rejects every request rather than allowing all.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.api.token');
        $provided = $request->bearerToken();

        $valid = is_string($expected)
            && $expected !== ''
            && is_string($provided)
            && hash_equals($expected, $provided);

        if (! $valid) {
            return response()->json(['message' => 'Invalid or missing API token.'], 401);
        }

        return $next($request);
    }
}
