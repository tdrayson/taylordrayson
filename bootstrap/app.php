<?php

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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
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
