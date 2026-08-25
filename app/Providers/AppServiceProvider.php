<?php

namespace App\Providers;

use App\Listeners\AlertOnFailedJob;
use App\Listeners\AlertOnScheduledTaskFailure;
use App\Queries\DayFoodTotals;
use App\Support\AmbientZone;
use App\Support\ApiHttp;
use App\Support\FeedDiscovery;
use App\Support\OptimisingFileAdder;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\FileAdder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Every addMedia* path resolves its adder from the container, so this is
        // the one place an incoming image can be made WebP before it is stored.
        $this->app->bind(FileAdder::class, OptimisingFileAdder::class);

        // Scoped so a sync writing hundreds of entries reads the phone's last
        // reading once rather than once per row.
        $this->app->scoped(AmbientZone::class);

        // Held for the request so every food card on a page shares one read of
        // the day totals.
        $this->app->scoped(DayFoodTotals::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('app', function (\Illuminate\View\View $view): void {
            $view->with('contextualFeeds', FeedDiscovery::forRoute(request()->route()));
        });

        // Failures that otherwise only ever reached the log.
        Event::listen(ScheduledTaskFailed::class, AlertOnScheduledTaskFailure::class);
        Event::listen(JobFailed::class, AlertOnFailedJob::class);

        // Say who we are on every outbound request: an unidentified default
        // Guzzle agent is a common thing for a bot filter to challenge.
        Http::globalRequestMiddleware(fn ($request) => $request->withHeader(
            'User-Agent',
            config('app.name').' (+'.config('app.url').')',
        ));

        // Shared retry policy for third-party clients. See ApiHttp.
        Http::macro('api', fn () => ApiHttp::pending());
    }
}
