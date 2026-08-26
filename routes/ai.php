<?php

use App\Mcp\Servers\SiteServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Middleware\CheckToken;

// Discovery and dynamic client registration. Throttled because registration is
// unauthenticated by design: a client may be created before anyone has signed
// in, and only becomes usable once the authorization code flow completes
// against a real session. Registering is therefore cheap to attempt and worth
// rate limiting; it grants nothing on its own.
Route::middleware('throttle:30,1')->group(function (): void {
    Mcp::oauthRoutes();
});

// Read-only, and gated twice: a valid Passport token, carrying the scope that
// only the MCP consent screen asks for.
Mcp::web('/mcp', SiteServer::class)
    ->middleware(['auth:api', CheckToken::using('mcp:use')]);
