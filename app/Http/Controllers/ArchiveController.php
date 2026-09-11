<?php

namespace App\Http\Controllers;

use App\Actions\BuildTimelineFeed;
use App\Enums\TimelineType;
use App\Models\Checkin;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\TimelineEntry;
use App\Queries\ArchiveTagBridge;
use App\Support\OgMeta;
use App\Timeline\TypeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ArchiveController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly BuildTimelineFeed $feed,
        private readonly ArchiveTagBridge $tagBridge,
    ) {}

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
            // Strict match: values are canonical slugs, so a loose compare would
            // let non-canonical numeric inputs (e.g. "03" for season 3) through
            // and render a duplicate page under the wrong label.
            abort_unless($taxonomy['values']()->pluck('value')->contains(fn (string $known): bool => $known === $value), 404);

            $parent = ['label' => $definition['label'], 'href' => '/'.$definition['slug']];
        }

        $page = TimelineEntry::query()
            ->whereHasMorph('timelineable', [$definition['model']], function (Builder $query) use ($taxonomy, $value) {
                if ($value !== null) {
                    ($taxonomy['filter'])($query, $value);
                }
            })
            ->withCardRelations()
            ->orderByDesc('occurred_at')
            ->paginate(self::PER_PAGE);

        $noun = $definition['noun'];
        $taxonomyLabel = $value !== null ? ($taxonomy['labelFor'])($value) : null;
        $accentToken = TimelineType::from($type)->accent();
        $title = $this->title($definition, $taxonomy, $taxonomyLabel);
        $subtitle = $page->total().' '.Str::plural($noun, $page->total());

        return Inertia::render('Archive', [
            'type' => $type,
            'accent' => $accentToken,
            'og' => OgMeta::archive($type, $definition['label'], $title, $accentToken, $value !== null, $noun, $page->total()),
            'title' => $title,
            'crumb' => $taxonomyLabel ?? $definition['label'],
            'subtitle' => $subtitle,
            'groups' => $this->feed->groupByDay(collect($page->items())),
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'chips' => $this->chips($definition, $value),
            'tagLink' => ($this->tagBridge)($value),
            'parent' => $parent,
            'map' => $page->currentPage() === 1 ? $this->overviewMap($type, $taxonomy, $value) : [],
        ]);
    }

    /**
     * Overview map payload for archives that support one (flights routes,
     * fuel stations). Empty for every other type.
     *
     * @param  array<string, mixed>|null  $taxonomy
     * @return list<array<string, mixed>>
     */
    private function overviewMap(string $type, ?array $taxonomy, ?string $value): array
    {
        return match ($type) {
            'flight' => $this->flightRoutes($taxonomy, $value),
            'fuel' => $this->fuelStations($taxonomy, $value),
            'checkin' => $this->checkinPlaces($taxonomy, $value),
            default => [],
        };
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
     * Unique fuel stations with coordinates for the overview map, filtered to
     * the active vehicle taxonomy when one is applied. Deduped by rounded
     * lat/lng so repeat fills at the same pump collapse to one pin.
     *
     * @param  array<string, mixed>|null  $taxonomy
     * @return list<array{lat: float, lng: float, label: string}>
     */
    private function fuelStations(?array $taxonomy, ?string $value): array
    {
        $query = Fuel::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($value !== null && $taxonomy !== null) {
            ($taxonomy['filter'])($query, $value);
        }

        return $query
            ->orderByDesc('occurred_at')
            ->get(['station_name', 'brand', 'city', 'latitude', 'longitude'])
            ->unique(fn (Fuel $fuel): string => round((float) $fuel->latitude, 4).','.round((float) $fuel->longitude, 4))
            ->map(fn (Fuel $fuel): array => [
                'lat' => (float) $fuel->latitude,
                'lng' => (float) $fuel->longitude,
                'label' => $fuel->station_name
                    ?: ($fuel->brand ? $fuel->brand.' garage' : null)
                    ?: $fuel->city
                    ?: 'Station',
            ])
            ->values()
            ->all();
    }

    /**
     * Check-in locations for the overview map, deduped to one point per venue
     * coordinate and filtered to the active category when one is applied. The
     * frontend clusters these, so every distinct place can be sent.
     *
     * @param  array<string, mixed>|null  $taxonomy
     * @return list<array<string, mixed>>
     */
    private function checkinPlaces(?array $taxonomy, ?string $value): array
    {
        $query = Checkin::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($value !== null && $taxonomy !== null) {
            ($taxonomy['filter'])($query, $value);
        }

        return $query
            ->orderByDesc('occurred_at')
            ->get(['venue_name', 'city', 'latitude', 'longitude'])
            ->unique(fn (Checkin $checkin): string => round((float) $checkin->latitude, 4).','.round((float) $checkin->longitude, 4))
            ->map(fn (Checkin $checkin): array => [
                'lat' => (float) $checkin->latitude,
                'lng' => (float) $checkin->longitude,
                'label' => $checkin->venue_name ?: $checkin->city ?: 'Place',
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

        $chips = $taxonomy['values']()
            ->map(fn (array $value): array => [
                'label' => $value['label'],
                'href' => '/'.$taxonomy['base'].'/'.$value['value'],
                'icon' => $value['icon'] ?? null,
                'active' => $value['value'] === $activeValue,
                'count' => $value['count'] ?? null,
            ])
            ->all();

        // An "All Places" / "All Flights" chip leads every filter, linking back
        // to the unfiltered archive and active when no value is set. A taxonomy
        // may override the label (e.g. "All Seasons") where the type label reads
        // awkwardly.
        array_unshift($chips, [
            'label' => $taxonomy['allLabel'] ?? 'All '.$definition['label'],
            'href' => '/'.$taxonomy['base'],
            'icon' => null,
            'active' => $activeValue === null,
            'count' => null,
            'all' => true,
        ]);

        return $chips;
    }
}
