<?php

namespace App\Http\Controllers;

use App\Actions\BuildTimelineFeed;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Calorie;
use App\Models\Checkin;
use App\Models\Flight;
use App\Models\Media;
use App\Models\Note;
use App\Models\Podcast;
use App\Models\Sleep;
use App\Models\TimelineEntry;
use App\Support\Distance;
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
            'podcastEpisodes' => Podcast::query()->count(),
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
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = (clone $start)->endOfYear()->endOfDay();

        return Inertia::render('Year', [
            'year' => $year,
            'og' => OgMeta::year($year),
            'entriesCount' => TimelineEntry::whereBetween('occurred_at', [$start, $end])->count(),
            'stats' => $this->periodStats($start, $end),
            'heatmap' => $this->heatmapDays($start, $end),
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
            'stats' => $this->periodStats($start, $end),
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
     * Entries per day for the contribution heatmap, keyed yyyy-mm-dd.
     *
     * @return array<string, int>
     */
    private function heatmapDays(Carbon $start, Carbon $end): array
    {
        return TimelineEntry::query()
            ->toBase()
            ->selectRaw('DATE(occurred_at) as date, COUNT(*) as total')
            ->whereBetween('occurred_at', [$start, $end])
            ->groupBy('date')
            ->pluck('total', 'date')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * Roll-up stat row shared by the year and month pages. Every stat
     * self-hides at zero, so sparse periods just show fewer numbers.
     *
     * @return array<int, array<string, mixed>>
     */
    private function periodStats(Carbon $start, Carbon $end): array
    {
        $between = fn ($query) => $query->whereBetween('occurred_at', [$start, $end]);

        $stats = [];

        $activities = $between(Activity::query())->count();

        if ($activities > 0) {
            $stats[] = ['label' => 'Activities', 'value' => number_format($activities)];

            $distanceKm = Distance::km((int) $between(Activity::query())->sum('distance')) ?? 0.0;

            if ($distanceKm > 0) {
                $stats[] = ['label' => 'Distance', 'value' => number_format($distanceKm), 'unit' => 'km'];
            }
        }

        $avgSleep = (int) round($between(Sleep::query())->avg('duration') ?? 0);

        if ($avgSleep > 0) {
            $stats[] = ['label' => 'Avg sleep', 'seconds' => $avgSleep];
        }

        $foodDays = (int) $between(Calorie::query())->toBase()->selectRaw('COUNT(DISTINCT DATE(occurred_at)) as days')->value('days');

        if ($foodDays > 0) {
            $stats[] = ['label' => 'Days of food', 'value' => number_format($foodDays)];
        }

        $films = $between(Media::query())->whereIn('type', ['film', 'show'])->count();

        if ($films > 0) {
            $stats[] = ['label' => 'Watched', 'value' => number_format($films)];
        }

        $flights = $between(Flight::query())->count();

        if ($flights > 0) {
            $stats[] = ['label' => 'Flights', 'value' => number_format($flights)];
        }

        $written = $between(Article::query())->where('published', true)->count() + $between(Note::query())->count();

        if ($written > 0) {
            $stats[] = ['label' => 'Written', 'value' => number_format($written)];
        }

        $places = $between(Checkin::query())->count();

        if ($places > 0) {
            $stats[] = ['label' => 'Places', 'value' => number_format($places)];
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

            $distanceKm = Distance::km((int) round($activities->sum('distance'))) ?? 0.0;

            if ($distanceKm > 0) {
                $stats[] = ['label' => 'Distance', 'value' => number_format($distanceKm, 1), 'unit' => 'km'];
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
