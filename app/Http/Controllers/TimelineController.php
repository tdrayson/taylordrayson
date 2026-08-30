<?php

namespace App\Http\Controllers;

use App\Actions\BuildMonthCalendar;
use App\Actions\BuildTimelineFeed;
use App\Models\Podcast;
use App\Models\TimelineEntry;
use App\Queries\DayStats;
use App\Queries\HeatmapDays;
use App\Queries\PeriodStats;
use App\Support\GalleryPhotos;
use App\Support\OgMeta;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\DeferProp;
use Inertia\Inertia;
use Inertia\Response;

class TimelineController extends Controller
{
    private const DAYS_PER_PAGE = 10;

    public function __construct(
        private readonly BuildTimelineFeed $feed,
        private readonly BuildMonthCalendar $monthCalendar,
        private readonly PeriodStats $periodStats,
        private readonly HeatmapDays $heatmapDays,
        private readonly DayStats $dayStats,
    ) {}

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
            ->orderByInstant($ascending ? 'asc' : 'desc')
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
            ->orderByInstant()
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

    /**
     * Whether this request is the first page of a paginated archive. The stats,
     * calendar, heatmap and photos all summarise the whole period, so later
     * pages of the feed neither render nor pay to build them.
     */
    private function isFirstPage(): bool
    {
        return Paginator::resolveCurrentPage() === 1;
    }

    public function year(int $year): Response
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = (clone $start)->endOfYear()->endOfDay();

        return Inertia::render('Year', [
            'year' => $year,
            'og' => OgMeta::year($year),
            'entriesCount' => TimelineEntry::whereBetween('occurred_at', [$start, $end])->count(),
            ...$this->isFirstPage() ? [
                'stats' => ($this->periodStats)($start, $end, withSuperlative: true),
                'heatmap' => ($this->heatmapDays)($start, $end),
            ] : [],
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
            ->orderByInstant('asc')
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null)
            ->values();

        return Inertia::render('Month', [
            'year' => $year,
            'month' => $month,
            'og' => OgMeta::month($year, $month),
            'entriesCount' => $entries->count(),
            ...$this->monthSummary($entries, $start, $end),
            ...$this->periodTail($start, $end),
        ]);
    }

    /**
     * Stats, day calendar and photos covering the whole month, or nothing on
     * later pages of the feed, which repeat neither.
     *
     * @param  Collection<int, TimelineEntry>  $entries
     * @return array<string, mixed>
     */
    private function monthSummary(Collection $entries, Carbon $start, Carbon $end): array
    {
        if (! $this->isFirstPage()) {
            return [];
        }

        $timelineables = $entries->map(fn (TimelineEntry $entry) => $entry->timelineable);

        /**
         * Batch-load `media` per model class before the photos payload reads it below,
         * so this fires one query per class rather than one per entry; loadMissing()
         * skips the classes cardRelations() already eager-loaded (Appearance, Activity, Article).
         */
        $timelineables->groupBy(fn ($model): string => $model::class)
            ->each(fn (Collection $group): EloquentCollection => EloquentCollection::make($group->values())->loadMissing('media'));

        return [
            'days' => ($this->monthCalendar)($entries, $start, $end),
            'stats' => ($this->periodStats)($start, $end),
            // Same shaped payload as the /photos gallery (masonry dimensions,
            // caption/accent, entry link) so the month strip shares its markup,
            // and the same rule about what counts as a photograph.
            'photos' => $timelineables
                ->filter(fn ($model): bool => GalleryPhotos::contributesPhotos($model))
                ->flatMap(fn ($model): array => GalleryPhotos::shape($model, $model->getMedia('cover')->merge($model->getMedia('photos'))))
                ->values()
                ->all(),
        ];
    }

    public function day(int $year, int $month, int $day): Response
    {
        $date = Carbon::create($year, $month, $day);

        // Day (and other non-timeline views) read chronologically, earliest first —
        // the inverse of the home timeline, which leads with the latest entry.
        $entries = TimelineEntry::query()
            ->withCardRelations()
            ->coveringDate($date->toDateString())
            ->orderByInstant('asc')
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null)
            ->values();

        return Inertia::render('Day', [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'og' => OgMeta::day($date),
            'items' => $this->feed->items($entries),
            'stats' => ($this->dayStats)($entries, $date),
            // No `rings` or `steps` until the daily figures are real (#67); they
            // were fixed placeholders. Day.vue hides the markup when they are absent.
        ]);
    }
}
