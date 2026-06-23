<?php

namespace App\Http\Controllers;

use App\Actions\BuildTimelineFeed;
use App\Models\Flight;
use App\Models\TimelineEntry;
use App\Timeline\TypeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ArchiveController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(private readonly BuildTimelineFeed $feed) {}

    public function index(string $type): Response
    {
        return $this->render($type);
    }

    /**
     * Route params bind positionally: the URL {value} comes before the `type` default,
     * so this signature order matters.
     */
    public function taxonomy(string $value, string $type): Response
    {
        return $this->render($type, $value);
    }

    private function render(string $type, ?string $value = null): Response
    {
        $definition = TypeRegistry::find($type);

        abort_if($definition === null, 404);

        $taxonomy = $definition['taxonomy'];
        $parent = null;

        if ($value !== null) {
            abort_if($taxonomy === null, 404);
            abort_unless($taxonomy['values']()->pluck('value')->contains($value), 404);

            $parent = ['label' => $definition['label'], 'href' => '/'.$definition['slug']];
        }

        $page = TimelineEntry::query()
            ->whereHasMorph('timelineable', [$definition['model']], function (Builder $query) use ($taxonomy, $value) {
                if ($value !== null) {
                    ($taxonomy['filter'])($query, $value);
                }
            })
            ->with(['timelineable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Flight::class => ['origin', 'destination', 'airline'],
            ])])
            ->orderByDesc('occurred_at')
            ->paginate(self::PER_PAGE);

        $noun = $definition['noun'];
        $taxonomyLabel = $value !== null ? ($taxonomy['labelFor'])($value) : null;

        return Inertia::render('Archive', [
            'type' => $type,
            'accent' => $type === 'calorie' ? 'food' : $type,
            'title' => $this->title($definition, $taxonomy, $taxonomyLabel),
            'crumb' => $taxonomyLabel ?? $definition['label'],
            'subtitle' => $page->total().' '.Str::plural($noun, $page->total()),
            'groups' => $this->feed->groupByDay(collect($page->items())),
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'chips' => $this->chips($definition, $value),
            'parent' => $parent,
            'map' => $type === 'flight' && $page->currentPage() === 1 ? $this->flightRoutes($taxonomy, $value) : [],
        ]);
    }

    /**
     * The page heading: the type label for an index, or a context-aware phrase
     * for a taxonomy (e.g. "Flights with EasyJet"), falling back to the value label.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>|null  $taxonomy
     */
    private function title(array $definition, ?array $taxonomy, ?string $taxonomyLabel): string
    {
        if ($taxonomyLabel === null) {
            return $definition['label'];
        }

        $template = $taxonomy['title'] ?? null;

        return $template !== null ? $template($taxonomyLabel) : $taxonomyLabel;
    }

    /**
     * Flight great-circle endpoints for the overview map, filtered to match the
     * active taxonomy (e.g. a single airline) when one is applied.
     *
     * @param  array<string, mixed>|null  $taxonomy
     * @return list<array{origin: array{lat: float, lng: float, iata: string}, destination: array{lat: float, lng: float, iata: string}}>
     */
    private function flightRoutes(?array $taxonomy, ?string $value): array
    {
        $query = Flight::query()->with(['origin', 'destination']);

        if ($value !== null && $taxonomy !== null) {
            ($taxonomy['filter'])($query, $value);
        }

        return $query->get()
            ->filter(fn (Flight $flight): bool => $flight->origin?->latitude !== null && $flight->destination?->latitude !== null)
            ->map(fn (Flight $flight): array => [
                'origin' => ['lat' => $flight->origin->latitude, 'lng' => $flight->origin->longitude, 'iata' => $flight->origin_iata],
                'destination' => ['lat' => $flight->destination->latitude, 'lng' => $flight->destination->longitude, 'iata' => $flight->destination_iata],
            ])
            ->values()
            ->all();
    }

    /**
     * Taxonomy values as navigable pills, with the currently applied value
     * flagged so it can render as the selected chip on taxonomy pages.
     *
     * @param  array<string, mixed>  $definition
     * @return array<int, array{label: string, href: string, icon: string|null, active: bool}>
     */
    private function chips(array $definition, ?string $activeValue = null): array
    {
        $taxonomy = $definition['taxonomy'];

        if ($taxonomy === null) {
            return [];
        }

        return $taxonomy['values']()
            ->map(fn (array $value): array => [
                'label' => $value['label'],
                'href' => '/'.$taxonomy['base'].'/'.$value['value'],
                'icon' => $value['icon'] ?? null,
                'active' => $value['value'] === $activeValue,
            ])
            ->all();
    }
}
