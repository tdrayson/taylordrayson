<?php

namespace App\Providers;

use App\Support\AmbientZone;
use App\Support\FeedDiscovery;
use App\Support\OptimisingFileAdder;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('app', function (\Illuminate\View\View $view): void {
            $view->with('contextualFeeds', FeedDiscovery::forRoute(request()->route()));
        });
    }
}
