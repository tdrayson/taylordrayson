<?php

namespace App\Http\Middleware;

use App\Queries\LoggingStreak;
use App\Queries\NowState;
use App\Support\OgRenderer;
use App\Support\Preferences;
use App\Support\StateStore;
use App\Support\TodaySteps;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appUrl' => rtrim((string) config('app.url'), '/'),
            // The current card design, appended to every generated og:image URL
            // so a template edit changes the URL and scrapers refetch. Cards are
            // served immutable, so without it a redesign is invisible to anyone
            // holding the old one.
            'ogVersion' => OgRenderer::generation(),
            // Colour scheme and unit choices, read from cookies so the first
            // render already matches what the visitor picked.
            'preferences' => Preferences::for($request),
            // Only whether someone is signed in, never the user record. The
            // client uses this to decide whether to offer an edit affordance;
            // every actual gate is enforced server-side, and sharing the model
            // would put the account's email in the props of every page.
            'signedIn' => $request->user() !== null,
            // Ambient readings from the phone. Shared rather than per-page
            // because the status bar carries battery, weather and rings on
            // every page, not just /now. One query for all four groups.
            'ambient' => fn (): array => (new NowState(new StateStore))(),
            // Steps as fetched from Rovi, which is a separate source from the
            // count the phone pushes into `ambient.rings`: the sync runs even
            // on days no Shortcut fires. Shared for the same reason as above,
            // and null until the day's first sync.
            'todaySteps' => TodaySteps::get(),
            // The food-logging streak, shown in the sidebar on every page.
            // Lazy so the query is skipped on a partial reload that does not
            // ask for it; cached until midnight either way.
            'streakDays' => fn (): int => app(LoggingStreak::class)(),
        ];
    }
}
