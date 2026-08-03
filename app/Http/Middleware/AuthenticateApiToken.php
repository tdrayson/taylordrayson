<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            // TEMPORARY diagnostic: shape only, never the token itself.
            $header = (string) $request->header('Authorization', '');
            Log::info('api token rejected', [
                'has_auth_header' => $header !== '',
                'header_prefix' => substr($header, 0, 7),
                'bearer_parsed' => is_string($provided),
                'provided_length' => is_string($provided) ? strlen($provided) : null,
                'expected_length' => is_string($expected) ? strlen($expected) : null,
                'matches_trimmed' => is_string($provided) && is_string($expected) && trim($provided) === trim($expected),
                'user_agent' => substr((string) $request->userAgent(), 0, 60),
                'content_type' => (string) $request->header('Content-Type', ''),
            ]);

            return response()->json(['message' => 'Invalid or missing API token.'], 401);
        }

        return $next($request);
    }
}
