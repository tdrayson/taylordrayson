<?php

namespace App\Http\Controllers;

use App\Datasets\Datasets;
use App\Enums\DatasetKind;
use App\Models\TimelineEntry;
use App\Support\OgMeta;
use App\Timeline\TypeRegistry;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The /more directory: every destination that doesn't earn a sidebar slot,
 * headlined by the "what I track" index with live per-type counts, grouped
 * by dataset kind.
 */
class MoreController extends Controller
{
    public function __invoke(): Response
    {
        $counts = TimelineEntry::query()
            ->selectRaw('dataset, count(*) as total')
            ->groupBy('dataset')
            ->pluck('total', 'dataset');

        $rows = collect(TypeRegistry::all())
            ->map(fn (array $type, string $key): array => [
                'type' => $key,
                'label' => $type['label'],
                'href' => '/'.$type['slug'],
                'count' => (int) ($counts[$key] ?? 0),
            ]);

        $tracked = collect(DatasetKind::cases())
            ->map(fn (DatasetKind $kind): array => [
                'kind' => $kind->value,
                'label' => $kind->label(),
                'items' => $rows->filter(fn (array $row): bool => Datasets::for($row['type'])?->kind() === $kind)->values()->all(),
            ])
            ->filter(fn (array $group): bool => $group['items'] !== [])
            ->values()
            ->all();

        return Inertia::render('More', [
            'tracked' => $tracked,
            'og' => OgMeta::more(),
        ]);
    }
}
