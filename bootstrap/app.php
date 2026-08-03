<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\LeaderboardEntry;
use App\Models\TimelineEntry;
use App\Support\OgMeta;
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
        $middleware->trustProxies(at: '*');

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
            // TEMPORARY: surface which fields a phone's payload failed on.
            if ($response->getStatusCode() === 422 && $request->is('api/*')) {
                Log::info('api validation failed', [
                    'path' => $request->path(),
                    'errors' => $exception instanceof ValidationException ? $exception->errors() : null,
                    'sent_keys' => array_map(
                        fn ($value): mixed => is_array($value) ? array_keys($value) : gettype($value),
                        $request->all(),
                    ),
                ]);
            }

            if ($response->getStatusCode() === 404 && ! $request->expectsJson()) {
                $entries = Cache::remember('error.entry_count', now()->addHour(), fn (): int => TimelineEntry::count());
                $days = Cache::remember('error.day_count', now()->addHour(), fn (): int => TimelineEntry::query()
                    ->selectRaw('date(occurred_at) as day')
                    ->distinct()
                    ->pluck('day')
                    ->count());

                return Inertia::render('Error', [
                    'og' => OgMeta::error(404),
                    'status' => 404,
                    'entries' => $entries,
                    'days' => $days,
                    'leaderboard' => LeaderboardEntry::topEntries(5),
                ])
                    ->toResponse($request)
                    ->setStatusCode(404);
            }

            return $response;
        });
    })->create();
