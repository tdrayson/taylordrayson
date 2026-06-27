<?php

namespace App\Http\Controllers;

use App\Actions\BuildTimelineFeed;
use App\Models\Activity;
use App\Models\Calorie;
use App\Models\Flight;
use App\Models\Media;
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

    public function __construct(private readonly BuildTimelineFeed $feed) {}

    public function index(): Response
    {
        $days = TimelineEntry::query()
            ->toBase()
            ->selectRaw('DATE(occurred_at) as date')
            ->groupBy('date')
            ->orderByDesc('date')
            ->paginate(self::DAYS_PER_PAGE);

        $dates = collect($days->items())->pluck('date');

        $groups = $dates->isEmpty()
            ? []
            : $this->groupsForDates($dates->first(), $dates->last());

        return Inertia::render('Timeline', [
            'og' => OgMeta::timeline(),
            'groups' => $groups,
            'currentPage' => $days->currentPage(),
            'lastPage' => $days->lastPage(),
        ]);
    }

    /**
     * Build day-grouped timeline cards for every entry between two dates (inclusive).
     *
     * @return array<int, array{label: string, href: string, items: array<int, array<string, mixed>>}>
     */
    private function groupsForDates(string $newest, string $oldest): array
    {
        $entries = TimelineEntry::query()
            ->withCardRelations()
            ->whereDate('occurred_at', '<=', $newest)
            ->whereDate('occurred_at', '>=', $oldest)
            ->orderByDesc('occurred_at')
            ->get();

        return $this->feed->groupByDay($entries);
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

        $entries = TimelineEntry::query()
            ->withCardRelations()
            ->whereBetween('occurred_at', [$start, $end])
            ->orderBy('occurred_at')
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null)
            ->values();

        return Inertia::render('Month', [
            'year' => $year,
            'month' => $month,
            'og' => OgMeta::month($year, $month),
            'entriesCount' => $entries->count(),
            'days' => $this->monthDays($entries),
            'stats' => $this->monthStats($entries, $start, $end),
        ]);
    }

    /**
     * Per-day data for the calendar: sleep duration and the day's calorie total
     * (the everyday sub-stats) plus the type of each notable entry for icons —
     * sleep and food are kept out of the icon row since they happen daily and
     * would just clutter every cell.
     *
     * @param  Collection<int, TimelineEntry>  $entries
     * @return array<int, array{sleep: ?int, calories: ?int, types: array<int, string>}>
     */
    private function monthDays(Collection $entries): array
    {
        return $entries->groupBy(fn (TimelineEntry $entry): int => (int) $entry->occurred_at->format('j'))
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
            })->all();
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
        $entries = TimelineEntry::query()
            ->withCardRelations()
            ->whereDate('occurred_at', $date->toDateString())
            ->orderBy('occurred_at', 'asc')
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null)
            ->values();

        return Inertia::render('Day', [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'og' => OgMeta::day($date),
            'items' => $entries->map(fn (TimelineEntry $entry): array => $this->feed->cardItem($entry))->all(),
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
