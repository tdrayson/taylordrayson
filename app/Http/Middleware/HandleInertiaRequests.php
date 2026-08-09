<?php

namespace App\Http\Middleware;

use App\Queries\NowState;
use App\Support\StateStore;
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
            // Only whether someone is signed in, never the user record. The
            // client uses this to decide whether to offer an edit affordance;
            // every actual gate is enforced server-side, and sharing the model
            // would put the account's email in the props of every page.
            'signedIn' => $request->user() !== null,
            // Ambient readings from the phone. Shared rather than per-page
            // because the status bar carries battery, weather and rings on
            // every page, not just /now. One query for all four groups.
            'ambient' => fn (): array => (new NowState(new StateStore))(),
        ];
    }
}
