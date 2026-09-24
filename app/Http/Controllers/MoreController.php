<?php

namespace App\Http\Controllers;

use App\Models\TimelineEntry;
use App\Support\OgMeta;
use App\Timeline\TypeRegistry;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The /more directory: every destination that doesn't earn a sidebar slot,
 * headlined by the "what I track" index with live per-type counts.
 */
class MoreController extends Controller
{
    public function __invoke(): Response
    {
        $counts = TimelineEntry::query()
            ->selectRaw('dataset, count(*) as total')
            ->groupBy('dataset')
            ->pluck('total', 'dataset');

        $tracked = collect(TypeRegistry::all())
            ->map(fn (array $type, string $key): array => [
                'type' => $key,
                'label' => $type['label'],
                'href' => '/'.$type['slug'],
                'count' => (int) ($counts[$key] ?? 0),
            ])
            ->values()
            ->all();

        return Inertia::render('More', [
            'tracked' => $tracked,
            'total' => (int) $counts->sum(),
            'og' => OgMeta::more(),
        ]);
    }
}
