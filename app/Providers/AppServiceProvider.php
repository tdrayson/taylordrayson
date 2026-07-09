<?php

namespace App\Providers;

use App\Support\FeedDiscovery;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
