<?php

namespace App\Http\Middleware;

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
        $user = $request->user();

        return [
            ...parent::share($request),
            'appUrl' => rtrim((string) config('app.url'), '/'),
            'auth' => [
                'user' => $user ? ['name' => $user->name, 'email' => $user->email] : null,
            ],
            'cp' => [
                'nav' => $user ? $this->controlPanelNav() : [],
            ],
        ];
    }

    /**
     * Collections navigation for the control panel sidebar, grouped by section.
     *
     * @return array<int, array{group: string, items: array<int, array{label: string, slug: string}>}>
     */
    private function controlPanelNav(): array
    {
        return [
            ['group' => 'Timeline', 'items' => [
                ['label' => 'Activities', 'slug' => 'activities'],
                ['label' => 'Sleep', 'slug' => 'sleep'],
                ['label' => 'Food', 'slug' => 'food'],
                ['label' => 'Media', 'slug' => 'media'],
                ['label' => 'Events', 'slug' => 'events'],
                ['label' => 'Appearances', 'slug' => 'appearances'],
                ['label' => 'This Week With', 'slug' => 'this-week-with'],
                ['label' => 'Flights', 'slug' => 'flights'],
                ['label' => 'Places', 'slug' => 'places'],
                ['label' => 'Fuel', 'slug' => 'fuel'],
                ['label' => 'Projects', 'slug' => 'projects'],
                ['label' => 'Articles', 'slug' => 'articles'],
                ['label' => 'Notes', 'slug' => 'notes'],
            ]],
            ['group' => 'Reference', 'items' => [
                ['label' => 'Airlines', 'slug' => 'airlines'],
                ['label' => 'Airports', 'slug' => 'airports'],
                ['label' => 'Fuel stations', 'slug' => 'fuel-stations'],
            ]],
        ];
    }
}
