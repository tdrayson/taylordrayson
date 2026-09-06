<?php

namespace App\Http\Controllers;

use App\Actions\BuildMonthCalendar;
use App\Actions\BuildTimelineFeed;
use App\Models\TimelineEntry;
use App\Queries\DayStats;
use App\Queries\HeatmapDays;
use App\Queries\MonthsInYear;
use App\Queries\PeriodStats;
use App\Queries\PodcastEpisodeCount;
use App\Queries\TimelineWindow;
use App\Queries\TimelineYears;
use App\Support\DayBudget;
use App\Support\GalleryPhotos;
use App\Support\OgMeta;
use App\Support\SqlDate;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\DeferProp;
use Inertia\Inertia;
use Inertia\Response;

class TimelineController extends Controller
{
    public function __construct(
        private readonly BuildTimelineFeed $feed,
        private readonly BuildMonthCalendar $monthCalendar,
        private readonly PeriodStats $periodStats,
        private readonly HeatmapDays $heatmapDays,
        private readonly DayStats $dayStats,
        private readonly MonthsInYear $months,
        private readonly PodcastEpisodeCount $podcastEpisodes,
        private readonly TimelineWindow $window,
        private readonly TimelineYears $years,
    ) {}

    public function index(Request $request): Response
    {
        // Anchored to a date, not an offset: every entry logged today would
        // otherwise shift what `?page=7` points at, so a shared link rots.
        $window = ($this->window)(
            $this->cursor($request->query('before')),
            $this->cursor($request->query('after')),
        );

        $groups = $window['to'] === null
            ? []
            : $this->groupsForDates($window['to'], $window['from']);

        return Inertia::render('Timeline', [
            'og' => OgMeta::timeline(),
            'groups' => $groups,
            'range' => $window['to'] === null ? null : ['from' => $window['from'], 'to' => $window['to']],
            'olderUrl' => $window['olderThan'] === null ? null : '/?before='.$window['olderThan'],
            // The newest page is the bare URL, so the feed has one canonical front.
            'newerUrl' => $window['newerThan'] === null ? null : '/?after='.$window['newerThan'],
            'years' => ($this->years)(),
            'podcastEpisodes' => ($this->podcastEpisodes)(),
        ]);
    }

    /** A Y-m-d cursor from the query string, or null for anything else. */
    private function cursor(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return Carbon::hasFormat($value, 'Y-m-d') ? $value : null;
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
        $date = SqlDate::date('occurred_at');

        $days = TimelineEntry::query()
            ->toBase()
            ->selectRaw("{$date} as day, count(*) as total")
            ->whereBetween('occurred_at', [$start, $end])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn (object $row): array => ['day' => (string) $row->day, 'total' => (int) $row->total]);

        // Sized by what the days hold rather than by a fixed count: a month of
        // 584 entries was four pages of 146, which is a long scroll for a page.
        $pages = DayBudget::pages($days);
        $current = max(1, min((int) request()->query('page', '1'), max(1, $pages->count())));
        $dates = collect($pages->get($current - 1) ?? [])->pluck('day');

        return [
            'groups' => Inertia::defer(fn (): array => $dates->isEmpty()
                ? []
                : $this->groupsForDates($dates->last(), $dates->first(), true)),
            'currentPage' => $current,
            'lastPage' => max(1, $pages->count()),
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
            // On every page, not just the first: the heatmap below is the only
            // other way into a month, and it stops rendering past page one.
            'months' => ($this->months)($year),
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
