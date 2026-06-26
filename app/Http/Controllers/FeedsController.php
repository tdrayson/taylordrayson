<?php

namespace App\Http\Controllers;

use App\Timeline\FeedPresets;
use App\Timeline\TypeRegistry;
use Inertia\Inertia;
use Inertia\Response;

class FeedsController extends Controller
{
    /**
     * Render the "subscribe" page: pick a preset or toggle individual types to
     * build a custom feed URL. Types and presets come straight from the registry
     * so the page never drifts from the feeds it produces.
     */
    public function index(): Response
    {
        return Inertia::render('Feeds', [
            'types' => $this->types(),
            'presets' => $this->presets(),
        ]);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function types(): array
    {
        return collect(TypeRegistry::all())
            ->map(fn (array $definition, string $key): array => [
                'key' => $key,
                'label' => $definition['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, types: array<int, string>}>
     */
    private function presets(): array
    {
        return collect(FeedPresets::all())
            ->map(fn (array $preset, string $key): array => [
                'key' => $key,
                'label' => $preset['label'],
                'description' => $preset['description'],
                'types' => $preset['types'],
            ])
            ->values()
            ->all();
    }
}
