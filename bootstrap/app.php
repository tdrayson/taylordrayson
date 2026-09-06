<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\LeaderboardEntry;
use App\Models\TimelineEntry;
use App\Support\OgMeta;
use App\Support\Preferences;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // No proxy sits in front of the app: DNS points at the VPS and nginx
        // talks to PHP-FPM over a local socket, so REMOTE_ADDR is already the
        // real client. Trusting `*` meant believing a client-supplied
        // X-Forwarded-For instead, which nginx never sets here, making every
        // IP-keyed rate limiter and reaction identity spoofable by a header.
        $middleware->trustProxies(at: []);

        // Display preferences are written by the browser, so they arrive
        // unencrypted and would otherwise be discarded as tampered with.
        $middleware->encryptCookies(except: Preferences::cookieNames());

        // Laravel trims every string in a request, walking nested arrays as it
        // goes. A Portable Text body is nested arrays of authored prose, so it
        // was having the space either side of every link, and the indentation
        // of every code block, quietly removed on save.
        $middleware->trimStrings(except: ['content.*']);

        // The webmention endpoint is posted to by other people's sites, which
        // by definition hold no token of ours. The spec requires accepting a
        // form-encoded POST from anywhere, so CSRF cannot apply; the payload is
        // two URLs, and everything it leads to is verified by fetching the
        // source and looking for a link back.
        $middleware->validateCsrfTokens(except: ['webmention']);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'api.token' => AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An API route must answer in JSON whatever the client asked for. Apple
        // Shortcuts sends no Accept header, which would otherwise turn a
        // validation failure into a 302 redirect the phone cannot read.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            // A rejected payload is logged by field, never by value, so a
            // misbuilt shortcut can be diagnosed from the server without the
            // readings themselves (location included) landing in a log file.
            if ($response->getStatusCode() === 422 && $request->is('api/*')) {
                Log::info('api validation failed', [
                    'path' => $request->path(),
                    'errors' => $exception instanceof ValidationException ? array_keys($exception->errors()) : null,
                ]);
            }

            if ($response->getStatusCode() === 404 && ! $request->expectsJson()) {
                $entries = Cache::remember('error.entry_count', now()->addHour(), fn (): int => TimelineEntry::count());
                $days = Cache::remember('error.day_count', now()->addHour(), fn (): int => TimelineEntry::query()
                    ->selectRaw('date(occurred_at) as day')
                    ->distinct()
                    ->pluck('day')
                    ->count());

                // Cast: the Redis cache store hands numeric values back as
                // strings, and the page formats these with toLocaleString(),
                // which is a no-op on a string.
                return Inertia::render('Error', [
                    'og' => OgMeta::error(404),
                    'status' => 404,
                    'entries' => (int) $entries,
                    'days' => (int) $days,
                    'leaderboard' => LeaderboardEntry::topEntries(5),
                ])
                    ->toResponse($request)
                    ->setStatusCode(404);
            }

            return $response;
        });
    })->create();
