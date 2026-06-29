<?php

namespace App\Http\Controllers\Cp;

use App\Cp\ResourceRegistry;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private ResourceRegistry $registry) {}

    /**
     * Render the control panel dashboard with grouped collection counts.
     */
    public function index(): Response
    {
        $collections = [];

        foreach ($this->registry->all() as $resource) {
            $collections[$resource->group()][] = [
                'label' => $resource->pluralLabel(),
                'slug' => $resource->slug(),
                'count' => $resource->query()->count(),
            ];
        }

        $grouped = collect($collections)
            ->map(fn (array $items, string $group): array => ['group' => $group, 'items' => $items])
            ->values()
            ->all();

        return Inertia::render('Cp/Dashboard', ['collections' => $grouped]);
    }
}
