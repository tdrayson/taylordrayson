<?php

namespace App\Providers;

use App\DynamicTags\Ambient\AmbientTag;
use App\DynamicTags\DynamicTagRegistry;
use App\DynamicTags\Entries\EntriesCount;
use App\DynamicTags\Entries\EntriesFirst;
use App\DynamicTags\Entries\EntriesLatest;
use App\DynamicTags\Entries\EntriesPhoto;
use App\DynamicTags\Site\SiteEmail;
use App\DynamicTags\Site\SiteHome;
use App\DynamicTags\Site\SiteSocial;
use App\DynamicTags\Site\SiteUpdated;
use App\DynamicTags\Streaks\StreakCurrent;
use App\DynamicTags\Streaks\StreakLongest;
use App\Http\Controllers\ArchiveController;
use App\Listeners\AlertOnFailedJob;
use App\Listeners\AlertOnScheduledTaskFailure;
use App\Queries\DayFoodTotals;
use App\Queries\NowState;
use App\Support\AmbientZone;
use App\Support\ApiHttp;
use App\Support\FeedDiscovery;
use App\Support\OptimisingFileAdder;
use App\Support\ZoneHistory;
use App\Timeline\TypeRegistry;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Passport\Passport;
use Laravel\Passport\Scope;
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
        // reading, and the flight history behind it, once rather than per row.
        $this->app->scoped(AmbientZone::class);
        $this->app->scoped(ZoneHistory::class);

        // Held for the request so every food card on a page shares one read of
        // the day totals.
        $this->app->scoped(DayFoodTotals::class);

        // Scoped so the status-bar share and every ambient tag on an article
        // resolve the same instance, and StateStore::entries() runs once per
        // request no matter how many ambient readings are referenced.
        $this->app->scoped(NowState::class);

        // The single registration path for every dynamic tag, including ones
        // generated at runtime rather than written as classes: bind an
        // instance under its own key and tag that key the same way.
        $this->app->tag([EntriesCount::class, EntriesFirst::class, EntriesLatest::class, EntriesPhoto::class, StreakCurrent::class, StreakLongest::class, SiteEmail::class, SiteSocial::class, SiteHome::class, SiteUpdated::class], DynamicTagRegistry::CONTAINER_TAG);

        // Generated rather than classes: bind each instance under its own
        // name and tag that name the same way, so the field map stays the
        // single source and no ambient tag name is hardcoded here.
        foreach (AmbientTag::generate() as $tag) {
            $this->app->instance($tag->name(), $tag);
            $this->app->tag([$tag->name()], DynamicTagRegistry::CONTAINER_TAG);
        }

        $this->app->singleton(DynamicTagRegistry::class);

        $this->registerArchiveRoutes();
    }

    /**
     * `Route::archives()`, registering each type's archive page, its /stats
     * redirect and its taxonomy sub-route.
     *
     * A macro so routes/web.php stays a flat list of controllers rather than
     * reaching into the type registry to build routes. In register() rather
     * than boot(), because the route files are loaded during the framework's
     * own boot and the macro has to exist before web.php is evaluated.
     *
     * Order inside is load-bearing and matches what the loop did: the /stats
     * redirect is registered before the taxonomy route, so "stats" is not
     * matched as a taxonomy value.
     */
    private function registerArchiveRoutes(): void
    {
        Route::macro('archives', function (): void {
            foreach (TypeRegistry::all() as $type => $definition) {
                Route::get($definition['slug'], [ArchiveController::class, 'index'])
                    ->defaults('type', $type)->name("archive.{$definition['slug']}");

                Route::redirect($definition['slug'].'/stats', '/stats/'.$definition['slug'], 301);

                if ($taxonomy = $definition['taxonomy']) {
                    // Its own name prefix: a taxonomy base usually matches the
                    // type's own slug, so naming it archive.* too would collide
                    // and route:cache refuses a table with duplicates.
                    Route::get($taxonomy['base'].'/{value}', [ArchiveController::class, 'taxonomy'])
                        ->defaults('type', $type)->name("taxonomy.{$taxonomy['base']}");
                }
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Passport ships no consent screen, so the OAuth flow 500s without one.
        // Rendered through Inertia to match the rest of the site; the approve
        // and deny controls inside it are plain forms, because completing the
        // request redirects to the client and an XHR visit cannot follow that.
        Passport::authorizationView(fn (array $parameters) => Inertia::render('Auth/Authorize', [
            'client' => $parameters['client']->name,
            'scopes' => array_map(fn (Scope $scope): array => [
                'id' => $scope->id,
                'description' => $scope->description,
            ], $parameters['scopes']),
            'authToken' => $parameters['authToken'],
            'csrf' => csrf_token(),
        ]));

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
