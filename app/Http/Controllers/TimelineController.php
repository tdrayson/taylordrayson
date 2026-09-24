<?php

namespace App\Http\Controllers;

use App\Actions\BuildMonthCalendar;
use App\Actions\BuildTimelineFeed;
use App\Models\TimelineEntry;
use App\Queries\DayStats;
use App\Queries\HeatmapDays;
use App\Queries\MonthsInYear;
use App\Queries\PeriodStats;
use App\Queries\ThisWeekWithEpisodeCount;
use App\Queries\TimelinePage;
use App\Queries\TimelineYears;
use App\Support\FeedInteractions;
use App\Support\GalleryPhotos;
use App\Support\OgMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TimelineController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly BuildTimelineFeed $feed,
        private readonly BuildMonthCalendar $monthCalendar,
        private readonly PeriodStats $periodStats,
        private readonly HeatmapDays $heatmapDays,
        private readonly DayStats $dayStats,
        private readonly MonthsInYear $months,
        private readonly ThisWeekWithEpisodeCount $thisWeekWithEpisodes,
        private readonly TimelinePage $page,
        private readonly TimelineYears $years,
    ) {}

    public function index(Request $request): Response
    {
        $page = ($this->page)(
            $this->cursor($request->query('before'), '00:00:00'),
            $this->cursor($request->query('after'), '23:59:59'),
        );

        $groups = $this->feed->groupByDay($page->entries);
        $dates = collect($groups)->pluck('date');

        return Inertia::render('Timeline', [
            'og' => OgMeta::timeline(),
            'groups' => $groups,
            'interactions' => FeedInteractions::defer($page->entries),
            'range' => $dates->isEmpty() ? null : ['from' => $dates->last(), 'to' => $dates->first()],
            'olderUrl' => $page->olderThan === null ? null : '/?before='.$page->olderThan,
            // The newest page is the bare URL, so the feed has one canonical front.
            'newerUrl' => $page->newerThan === null ? null : '/?after='.$page->newerThan,
            'years' => ($this->years)(),
            'thisWeekWithEpisodes' => ($this->thisWeekWithEpisodes)(),
        ]);
    }

    /**
     * An instant cursor from the query string, or null for anything else.
     *
     * @param  string  $time  Filled in for a bare date, which older links carry.
     */
    private function cursor(mixed $value, string $time): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2})?$/', $value) !== 1) {
            return null;
        }

        $value = strlen($value) === 10 ? "{$value}T{$time}" : $value;

        return Carbon::hasFormat($value, 'Y-m-d\TH:i:s') ? str_replace('T', ' ', $value) : null;
    }

    /**
     * Chronological timeline tail for a period, paginated by entry.
     *
     * @return array{groups: array<int, mixed>, interactions: mixed, currentPage: int, lastPage: int}
     */
    private function periodTail(Carbon $start, Carbon $end): array
    {
        return $this->paginatedFeed(
            TimelineEntry::query()->whereBetween('occurred_at', [$start, $end])->orderByInstant('asc'),
        );
    }

    /**
     * One page of an ordered feed query, grouped by day.
     *
     * @param  Builder<TimelineEntry>  $query
     * @return array{groups: array<int, mixed>, interactions: mixed, currentPage: int, lastPage: int}
     */
    private function paginatedFeed(Builder $query): array
    {
        $page = $query->clone()->withCardRelations()->paginate(self::PER_PAGE);
        $entries = collect($page->items());

        return [
            // Resolved in the response rather than deferred: the feed carries
            // the page's h-feed, which SSR has to render for parsers.
            'groups' => $this->feed->groupByDay($entries),
            'interactions' => FeedInteractions::defer($entries),
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
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
            ->filter(fn (TimelineEntry $entry): bool => $entry->entry !== null)
            ->values();

        $years = $entries->map(fn (TimelineEntry $entry): string => $entry->occurred_at->format('Y'))->unique();

        return Inertia::render('OnThisDay', [
            'og' => OgMeta::onThisDay($today),
            'date' => $today->format('j F'),
            'entriesCount' => $entries->count(),
            'yearsCount' => $years->count(),
            'groups' => $this->feed->groupByDay($entries),
            'interactions' => FeedInteractions::defer($entries),
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
            ->filter(fn (TimelineEntry $entry): bool => $entry->entry !== null)
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

        $models = $entries->map(fn (TimelineEntry $entry) => $entry->entry);

        /**
         * Batch-load `media` per model class before the photos payload reads it below,
         * so this fires one query per class rather than one per entry; loadMissing()
         * skips the classes cardRelations() already eager-loaded (Appearance, Activity, Article).
         */
        $models->groupBy(fn ($model): string => $model::class)
            ->each(fn (Collection $group): EloquentCollection => EloquentCollection::make($group->values())->loadMissing('media'));

        return [
            'days' => ($this->monthCalendar)($entries, $start, $end),
            'stats' => ($this->periodStats)($start, $end),
            // Same shaped payload as the /photos gallery (masonry dimensions,
            // caption/accent, entry link) so the month strip shares its markup,
            // and the same rule about what counts as a photograph.
            'photos' => $models
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
            ->filter(fn (TimelineEntry $entry): bool => $entry->entry !== null)
            ->values();

        return Inertia::render('Day', [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'og' => OgMeta::day($date),
            'items' => $this->feed->items($entries),
            'interactions' => FeedInteractions::defer($entries),
            'stats' => ($this->dayStats)($entries, $date),
            // No `rings` or `steps` until the daily figures are real (#67); they
            // were fixed placeholders. Day.vue hides the markup when they are absent.
        ]);
    }
}
