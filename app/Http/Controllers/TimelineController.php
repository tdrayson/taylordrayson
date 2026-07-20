<?php

namespace App\Http\Controllers;

use App\Actions\BuildTimelineFeed;
use App\Enums\ActivityDiscipline;
use App\Enums\MediaType;
use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Calorie;
use App\Models\Checkin;
use App\Models\Flight;
use App\Models\Media;
use App\Models\Note;
use App\Models\Podcast;
use App\Models\Sleep;
use App\Models\TimelineEntry;
use App\Support\GalleryPhotos;
use App\Support\OgMeta;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\DeferProp;
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
    private function groupsForDates(string $newest, string $oldest, bool $ascending = false): array
    {
        $entries = TimelineEntry::query()
            ->withCardRelations()
            ->whereDate('occurred_at', '<=', $newest)
            ->whereDate('occurred_at', '>=', $oldest)
            ->orderBy('occurred_at', $ascending ? 'asc' : 'desc')
            ->get();

        return $this->feed->groupByDay($entries);
    }

    /**
     * Day-paginated, chronological timeline tail for a period. Returns the
     * pagination metadata immediately and the (expensive) hydrated groups as
     * a deferred closure, so the archive's stats paint before its feed.
     *
     * @return array{groups: DeferProp, currentPage: int, lastPage: int}
     */
    private function periodTail(Carbon $start, Carbon $end): array
    {
        $days = TimelineEntry::query()
            ->toBase()
            ->selectRaw('DATE(occurred_at) as date')
            ->whereBetween('occurred_at', [$start, $end])
            ->groupBy('date')
            ->orderBy('date')
            ->paginate(self::DAYS_PER_PAGE);

        $dates = collect($days->items())->pluck('date');

        return [
            'groups' => Inertia::defer(fn (): array => $dates->isEmpty()
                ? []
                : $this->groupsForDates($dates->last(), $dates->first(), true)),
            'currentPage' => $days->currentPage(),
            'lastPage' => $days->lastPage(),
        ];
    }

    /**
     * "On this day": every entry that shares today's month and day, across all
     * years. groupByDay keys on Y-m-d, so each year lands in its own group and
     * the date headers link back to that specific day.
     */
    public function onThisDay(): Response
    {
        $today = Carbon::today();

        $entries = TimelineEntry::query()
            ->withCardRelations()
            ->coveringAnniversary($today->format('m-d'))
            ->orderByDesc('occurred_at')
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null)
            ->values();

        $years = $entries->map(fn (TimelineEntry $entry): string => $entry->occurred_at->format('Y'))->unique();

        return Inertia::render('OnThisDay', [
            'og' => OgMeta::onThisDay($today),
            'date' => $today->format('j F'),
            'entriesCount' => $entries->count(),
            'yearsCount' => $years->count(),
            'groups' => $this->feed->groupByDay($entries),
        ]);
    }

    public function year(int $year): Response
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = (clone $start)->endOfYear()->endOfDay();

        return Inertia::render('Year', [
            'year' => $year,
            'og' => OgMeta::year($year),
            'entriesCount' => TimelineEntry::whereBetween('occurred_at', [$start, $end])->count(),
            'stats' => $this->periodStats($start, $end, withSuperlative: true),
            'heatmap' => $this->heatmapDays($start, $end),
            ...$this->periodTail($start, $end),
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

        $timelineables = $entries->map(fn (TimelineEntry $entry) => $entry->timelineable);

        /**
         * Batch-load `media` per model class before the photos payload reads it below,
         * so this fires one query per class rather than one per entry; loadMissing()
         * skips the classes cardRelations() already eager-loaded (Appearance, Activity, Article).
         */
        $timelineables->groupBy(fn ($model): string => $model::class)
            ->each(fn (Collection $group): EloquentCollection => EloquentCollection::make($group->values())->loadMissing('media'));

        return Inertia::render('Month', [
            'year' => $year,
            'month' => $month,
            'og' => OgMeta::month($year, $month),
            'entriesCount' => $entries->count(),
            'days' => $this->monthDays($entries, $start, $end),
            'stats' => $this->periodStats($start, $end),
            // Same shaped payload as the /photos gallery (masonry dimensions,
            // caption/accent, entry link) so the month strip shares its markup.
            // Appearance covers are derived video thumbnails, excluded like /photos does.
            'photos' => $timelineables
                ->reject(fn ($model): bool => $model instanceof Appearance)
                ->flatMap(fn ($model): array => GalleryPhotos::shape($model, $model->getMedia('cover')->merge($model->getMedia('photos'))))
                ->take(12)
                ->values()
                ->all(),
            ...$this->periodTail($start, $end),
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
    private function monthDays(Collection $entries, Carbon $start, Carbon $end): array
    {
        // Calories store one row per food item but only one spine entry per day,
        // so day totals must come straight from the calories table.
        $calorieTotals = Calorie::query()
            ->toBase()
            ->selectRaw('DATE(occurred_at) as date, SUM(calories) as total')
            ->whereBetween('occurred_at', [$start, $end])
            ->groupBy('date')
            ->pluck('total', 'date');

        return $entries->groupBy(fn (TimelineEntry $entry): int => (int) $entry->occurred_at->format('j'))
            ->map(function (Collection $group) use ($calorieTotals): array {
                $sleep = null;
                $types = [];

                foreach ($group as $entry) {
                    $model = $entry->timelineable;

                    if ($model instanceof Sleep) {
                        $sleep = $model->duration;

                        continue;
                    }

                    if ($model instanceof Calorie) {
                        continue;
                    }

                    $types[] = $model->card()->type->value;
                }

                $calories = (int) ($calorieTotals[$group->first()->occurred_at->toDateString()] ?? 0);

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
     * Roll-up stat row for the year and month pages. Every stat self-hides at
     * zero, so sparse periods just show fewer numbers. The year page passes
     * $withSuperlative to append a standout (e.g. the longest run), which reads
     * as a headline over a year but not over a single month.
     *
     * @return array<int, array<string, mixed>>
     */
    private function periodStats(Carbon $start, Carbon $end, bool $withSuperlative = false): array
    {
        $between = fn ($query) => $query->whereBetween('occurred_at', [$start, $end]);

        $stats = [];

        $activities = $between(Activity::query())->count();

        if ($activities > 0) {
            $stats[] = ['label' => 'Activities', 'value' => number_format($activities)];
        }

        /**
         * Distance split by discipline: a single lumped total hid that walking,
         * running and cycling are wildly different distances and efforts. Each
         * self-hides, so a period with only walks shows only "Walked".
         *
         * @var array<string, list<string>> $disciplines
         */
        $disciplines = [
            'Walked' => [ActivityDiscipline::Walk->value],
            'Ran' => [ActivityDiscipline::Run->value],
            'Cycled' => [ActivityDiscipline::Ride->value, ActivityDiscipline::EbikeRide->value],
        ];

        foreach ($disciplines as $label => $types) {
            $distanceM = (int) $between(Activity::query())->whereIn('type', $types)->sum('distance');

            if ($distanceM > 0) {
                $stats[] = ['label' => $label, 'distanceM' => $distanceM, 'precision' => 0];
            }
        }

        $avgSleep = (int) round($between(Sleep::query())->avg('duration') ?? 0);

        if ($avgSleep > 0) {
            $stats[] = ['label' => 'Avg sleep', 'seconds' => $avgSleep];
        }

        // Food as a daily rhythm (avg calories per logged day) rather than the
        // contextless "365 days logged": averaged over days that were logged, so
        // a partial period isn't diluted by untracked days.
        $foodDays = (int) $between(Calorie::query())->toBase()->selectRaw('COUNT(DISTINCT DATE(occurred_at)) as days')->value('days');

        if ($foodDays > 0) {
            $avgCalories = (int) round($between(Calorie::query())->sum('calories') / $foodDays);

            if ($avgCalories > 0) {
                $stats[] = ['label' => 'Food', 'value' => number_format($avgCalories), 'unit' => 'kcal/day'];
            }
        }

        $films = $between(Media::query())->whereIn('type', [MediaType::Film->value, MediaType::TvEpisode->value])->count();

        if ($films > 0) {
            $stats[] = ['label' => 'Watched', 'value' => number_format($films)];
        }

        $flights = $between(Flight::query())->count();

        if ($flights > 0) {
            $stats[] = ['label' => 'Flights', 'value' => number_format($flights)];
        }

        $places = $between(Checkin::query())->count();

        if ($places > 0) {
            $stats[] = ['label' => 'Places', 'value' => number_format($places)];
        }

        $written = $between(Article::query())->where('published', true)->count() + $between(Note::query())->count();

        if ($written > 0) {
            $stats[] = ['label' => 'Written', 'value' => number_format($written)];
        }

        // Year-scale superlative: the standout single run of the period.
        if ($withSuperlative) {
            $longestRun = (int) $between(Activity::query())->where('type', ActivityDiscipline::Run->value)->max('distance');

            if ($longestRun > 0) {
                $stats[] = ['label' => 'Longest run', 'distanceM' => $longestRun, 'precision' => 1];
            }
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
            ->coveringDate($date->toDateString())
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
     * @return array<int, array{label: string, value?: string, unit?: string, seconds?: int, distanceM?: int, precision?: int}>
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

            $distanceM = (int) round($activities->sum('distance'));

            if ($distanceM > 0) {
                $stats[] = ['label' => 'Distance', 'distanceM' => $distanceM, 'precision' => 1];
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
