<?php

namespace App\Http\Controllers;

use App\Actions\BuildTimelineFeed;
use App\Content\ContentEntry;
use App\Content\ContentRepository;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Calorie;
use App\Models\Flight;
use App\Models\Media;
use App\Models\Note;
use App\Models\Podcast;
use App\Models\Sleep;
use App\Models\TimelineEntry;
use App\Support\OgMeta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TimelineController extends Controller
{
    private const DAYS_PER_PAGE = 10;

    /** Eloquent morph types superseded by Statamic; excluded from TimelineEntry queries. */
    private const EXCLUDED_MORPH_TYPES = [Article::class, Note::class];

    public function __construct(
        private readonly BuildTimelineFeed $feed,
        private readonly ContentRepository $content,
    ) {}

    public function index(): Response
    {
        // Build a unified, sorted list of distinct dates from both Eloquent and Statamic,
        // then paginate that list so Statamic-only days are counted and shown.
        $allDates = $this->unifiedDistinctDates();

        $total = $allDates->count();
        $perPage = self::DAYS_PER_PAGE;
        $currentPage = (int) request()->query('page', 1);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min($currentPage, $lastPage);

        $pageDates = $allDates->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $groups = $pageDates->isEmpty()
            ? []
            : $this->groupsForDates($pageDates->first(), $pageDates->last());

        return Inertia::render('Timeline', [
            'og' => OgMeta::timeline(),
            'groups' => $groups,
            'currentPage' => $currentPage,
            'lastPage' => $lastPage,
            'podcastEpisodes' => Podcast::query()->count(),
        ]);
    }

    /**
     * Return a sorted (newest-first) Collection of distinct date strings ('Y-m-d')
     * from the union of Eloquent TimelineEntry rows (excluding Article/Note morphs)
     * and all published Statamic articles and notes.
     *
     * @return Collection<int, string>
     */
    private function unifiedDistinctDates(): Collection
    {
        // Distinct dates from Eloquent (Article/Note morph types excluded).
        $eloquentDates = TimelineEntry::query()
            ->toBase()
            ->selectRaw('DATE(occurred_at) as date')
            ->whereNotIn('timelineable_type', self::EXCLUDED_MORPH_TYPES)
            ->groupBy('date')
            ->orderByDesc('date')
            ->get()
            ->pluck('date');

        // Distinct dates from Statamic content (betweenDates needs bounds).
        $contentDates = $this->content->all()
            ->map(fn (ContentEntry $e): string => $e->occurredAt()->toDateString())
            ->unique()
            ->values();

        return $eloquentDates
            ->merge($contentDates)
            ->unique()
            ->sort()
            ->reverse()
            ->values();
    }

    /**
     * Build day-grouped timeline cards for every entry between two dates (inclusive).
     * Merges Eloquent cards (Article/Note morphs excluded) with Statamic content cards,
     * sorts the combined set newest-first, then groups by day.
     *
     * @return array<int, array{label: string, href: string, items: array<int, array<string, mixed>>}>
     */
    private function groupsForDates(string $newest, string $oldest): array
    {
        // Eloquent entries — exclude Article/Note morph types to avoid duplicates.
        $entries = TimelineEntry::query()
            ->withCardRelations()
            ->whereNotIn('timelineable_type', self::EXCLUDED_MORPH_TYPES)
            ->whereDate('occurred_at', '<=', $newest)
            ->whereDate('occurred_at', '>=', $oldest)
            ->orderByDesc('occurred_at')
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null);

        // Map Eloquent entries to card items, carrying occurred_at for sorting.
        // Convert to a base Collection so merge() with content cards (plain arrays) works.
        $eloquentCards = collect($entries->map(function (TimelineEntry $entry): array {
            $item = $this->feed->cardItem($entry);
            $item['_occurred_at'] = $entry->occurred_at;

            return $item;
        })->all());

        // Statamic content cards for the same date window.
        $newestCarbon = Carbon::parse($newest)->endOfDay();
        $oldestCarbon = Carbon::parse($oldest)->startOfDay();

        $contentCards = $this->content
            ->betweenDates($newestCarbon, $oldestCarbon)
            ->map(fn (ContentEntry $e): array => $this->feed->contentCardItem($e));

        // Merge and sort newest-first.
        $merged = $eloquentCards
            ->values()
            ->merge($contentCards->values())
            ->sortByDesc(fn (array $item): int => $item['_occurred_at']->timestamp)
            ->values();

        // Group by day and build the group payload.
        return $merged
            ->groupBy(fn (array $item): string => $item['_occurred_at']->format('Y-m-d'))
            ->map(function (Collection $group): array {
                $date = $group->first()['_occurred_at'];

                // Strip the internal sort key before sending to the frontend.
                $items = $group->map(function (array $item): array {
                    unset($item['_occurred_at']);

                    return $item;
                })->values()->all();

                return [
                    'label' => $date->format('l j F Y'),
                    'date' => $date->format('Y-m-d'),
                    'href' => '/'.$date->format('Y/m/d'),
                    'items' => $items,
                ];
            })
            ->values()
            ->all();
    }

    public function year(int $year): Response
    {
        return Inertia::render('Year', [
            'year' => $year,
            'og' => OgMeta::year($year),
        ]);
    }

    public function month(int $year, int $month): Response
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = (clone $start)->endOfMonth()->endOfDay();

        // Exclude Article/Note morph types — Statamic content provides those entries.
        $entries = TimelineEntry::query()
            ->withCardRelations()
            ->whereNotIn('timelineable_type', self::EXCLUDED_MORPH_TYPES)
            ->whereBetween('occurred_at', [$start, $end])
            ->orderBy('occurred_at')
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null)
            ->values();

        // Published Statamic articles and notes for the same month window.
        $contentEntries = $this->content->betweenDates($end->copy()->endOfDay(), $start->copy()->startOfDay());

        return Inertia::render('Month', [
            'year' => $year,
            'month' => $month,
            'og' => OgMeta::month($year, $month),
            'entriesCount' => $entries->count() + $contentEntries->count(),
            'days' => $this->monthDays($entries, $contentEntries),
            'stats' => $this->monthStats($entries, $start, $end),
        ]);
    }

    /**
     * Per-day data for the calendar: sleep duration and the day's calorie total
     * (the everyday sub-stats) plus the type of each notable entry for icons —
     * sleep and food are kept out of the icon row since they happen daily and
     * would just clutter every cell.
     *
     * Content entries (Statamic articles/notes) contribute their type to the
     * icon row for the day they occurred on.
     *
     * @param  Collection<int, TimelineEntry>  $entries
     * @param  Collection<int, ContentEntry>  $contentEntries
     * @return array<int, array{sleep: ?int, calories: ?int, types: array<int, string>}>
     */
    private function monthDays(Collection $entries, ?Collection $contentEntries = null): array
    {
        // Build the base day map from Eloquent entries.
        $days = $entries->groupBy(fn (TimelineEntry $entry): int => (int) $entry->occurred_at->format('j'))
            ->map(function (Collection $group): array {
                $sleep = null;
                $calories = 0;
                $types = [];

                foreach ($group as $entry) {
                    $model = $entry->timelineable;

                    if ($model instanceof Sleep) {
                        $sleep = $model->duration;

                        continue;
                    }

                    if ($model instanceof Calorie) {
                        $calories += $model->calories;

                        continue;
                    }

                    $types[] = $model->card()['type'];
                }

                return ['sleep' => $sleep, 'calories' => $calories ?: null, 'types' => $types];
            });

        // Fold Statamic content entries into the day map — they contribute their
        // type ('article'|'note') to the icon row; sleep/calories are unaffected.
        foreach ($contentEntries ?? [] as $contentEntry) {
            $dayOfMonth = (int) $contentEntry->occurredAt()->format('j');
            $existing = $days->get($dayOfMonth, ['sleep' => null, 'calories' => null, 'types' => []]);
            $existing['types'][] = $contentEntry->card()['type'];
            $days->put($dayOfMonth, $existing);
        }

        return $days->all();
    }

    /**
     * Monthly roll-up stats.
     *
     * @param  Collection<int, TimelineEntry>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function monthStats(Collection $entries, Carbon $start, Carbon $end): array
    {
        $models = $entries->map->timelineable;
        $activities = $models->filter(fn ($model): bool => $model instanceof Activity);
        $sleeps = $models->filter(fn ($model): bool => $model instanceof Sleep);
        $flights = $models->filter(fn ($model): bool => $model instanceof Flight);
        $films = $models->filter(fn ($model): bool => $model instanceof Media && $model->type === 'film');

        $stats = [];

        if ($activities->isNotEmpty()) {
            $stats[] = ['label' => 'Activities', 'value' => (string) $activities->count()];

            $distance = round((float) $activities->sum('distance_km'));

            if ($distance > 0) {
                $stats[] = ['label' => 'Distance', 'value' => number_format($distance), 'unit' => 'km'];
            }
        }

        if ($sleeps->isNotEmpty()) {
            $stats[] = ['label' => 'Avg sleep', 'seconds' => (int) round($sleeps->avg('duration'))];
        }

        if ($flights->isNotEmpty()) {
            $stats[] = ['label' => 'Flights', 'value' => (string) $flights->count()];
        }

        if ($films->isNotEmpty()) {
            $stats[] = ['label' => 'Films', 'value' => (string) $films->count()];
        }

        $calories = (int) Calorie::query()->whereBetween('occurred_at', [$start, $end])->sum('calories');

        if ($calories > 0) {
            $stats[] = ['label' => 'Calories', 'value' => number_format($calories), 'unit' => 'kcal'];
        }

        return $stats;
    }

    public function day(int $year, int $month, int $day): Response
    {
        $date = Carbon::create($year, $month, $day);

        // Day (and other non-timeline views) read chronologically, earliest first —
        // the inverse of the home timeline, which leads with the latest entry.
        // Exclude Article/Note morph types — Statamic content provides those entries.
        $entries = TimelineEntry::query()
            ->withCardRelations()
            ->whereNotIn('timelineable_type', self::EXCLUDED_MORPH_TYPES)
            ->whereDate('occurred_at', $date->toDateString())
            ->orderBy('occurred_at', 'asc')
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null)
            ->values();

        // Map Eloquent entries to card items, carrying occurred_at for stable sort.
        // Convert to a base Collection so merge() with content cards (plain arrays) works;
        // Eloquent's Collection::merge() calls getKey() on items, which fails for arrays.
        $eloquentCards = collect($entries->map(function (TimelineEntry $entry): array {
            $item = $this->feed->cardItem($entry);
            $item['_occurred_at'] = $entry->occurred_at;

            return $item;
        })->all());

        // Published Statamic content for the same day, mapped to card items.
        $contentCards = $this->content
            ->betweenDates($date->copy()->endOfDay(), $date->copy()->startOfDay())
            ->map(fn (ContentEntry $e): array => $this->feed->contentCardItem($e));

        // Merge and sort earliest-first (day view is chronological).
        $items = $eloquentCards
            ->values()
            ->merge($contentCards->values())
            ->sortBy(fn (array $item): int => $item['_occurred_at']->timestamp)
            ->values()
            ->map(function (array $item): array {
                unset($item['_occurred_at']);

                return $item;
            })
            ->all();

        return Inertia::render('Day', [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'og' => OgMeta::day($date),
            'items' => $items,
            'stats' => $this->dayStats($entries, $date),
            // Placeholder Apple-Health summary — replace with real data once the health schema lands.
            'rings' => ['move' => 62, 'exercise' => 53, 'stand' => 75, 'moveKcal' => 137, 'exerciseMins' => 32, 'standHrs' => 9],
            'steps' => 11240,
        ]);
    }

    /**
     * Summary stats for a day, derived from the entries we actually store.
     *
     * @param  Collection<int, TimelineEntry>  $entries
     * @return array<int, array{label: string, value?: string, unit?: string, seconds?: int}>
     */
    private function dayStats(Collection $entries, Carbon $date): array
    {
        $models = $entries->map->timelineable;
        $sleep = $models->first(fn ($model): bool => $model instanceof Sleep);
        $activities = $models->filter(fn ($model): bool => $model instanceof Activity);

        $stats = [];

        if ($sleep instanceof Sleep) {
            $stats[] = ['label' => 'Slept', 'seconds' => $sleep->duration];
        }

        if ($activities->isNotEmpty()) {
            $stats[] = ['label' => 'Activities', 'value' => (string) $activities->count()];

            $distance = (float) $activities->sum('distance_km');

            if ($distance > 0) {
                $stats[] = ['label' => 'Distance', 'value' => number_format($distance, 1), 'unit' => 'km'];
            }
        }

        // Calories are stored one row per food item, so total the whole day directly
        // rather than the single representative row carried on the timeline entry.
        $calories = (int) Calorie::query()->whereDate('occurred_at', $date->toDateString())->sum('calories');

        if ($calories > 0) {
            $stats[] = ['label' => 'Food', 'value' => number_format($calories), 'unit' => 'kcal'];
        }

        return $stats;
    }
}
