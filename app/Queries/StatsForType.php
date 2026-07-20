<?php

namespace App\Queries;

use App\Enums\ActivityDiscipline;
use App\Models\Activity;
use App\Support\Distance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Computes the full /stats/{type} dashboard payload for a range: totals,
 * deltas against a comparison window, trend series, and the supporting
 * breakdowns (by-type, busiest times, records, route map).
 */
final class StatsForType
{
    /** Cap on route polylines sent to the map, to keep the payload sane. */
    private const ROUTE_LIMIT = 250;

    /** @var list<string> */
    private const COMPARE_MODES = ['previous-period', 'previous-year', 'none'];

    /**
     * @return array{
     *     range: array{from: string, to: string, label: string},
     *     compare: array{mode: string, label: ?string},
     *     metrics: list<array<string, mixed>>,
     *     averageLabel: string,
     *     perWeek: list<array{label: string, display?: string, distanceM?: int, precision?: int}>,
     *     byType: list<array{label: string, value: int}>,
     *     busiest: array{grid: list<list<int>>, max: int},
     *     trend: array<string, mixed>,
     *     records: list<array{label: string, value?: string, distanceM?: int, precision?: int}>,
     *     routes: list<string>,
     * }
     */
    public function __invoke(string $type, Carbon $start, Carbon $end, string $compareMode): array
    {
        $mode = $this->normalisedCompareMode($compareMode);
        $window = $this->comparisonWindow($mode, $start, $end);

        $days = $this->days($start, $end);
        $plan = $this->plan(count($days));
        $multiYear = $start->year !== $end->year;

        $totals = $this->sumDays($days);
        $compareTotals = $window ? $this->sumDays($this->days($window[0], $window[1])) : null;

        return [
            'range' => ['from' => $start->toDateString(), 'to' => $end->toDateString(), 'label' => $this->rangeLabel($start, $end)],
            'compare' => ['mode' => $mode, 'label' => $this->compareLabel($mode)],
            'metrics' => $this->metrics($totals, $compareTotals, $this->series($days, $plan['spark'], $multiYear)),
            'averageLabel' => $plan['averageLabel'],
            'perWeek' => $this->averages($totals, $plan['divisor']),
            'byType' => $this->byType($start, $end),
            'busiest' => $this->busiest($start, $end),
            'trend' => $this->trend($days, $plan, $multiYear),
            'records' => $this->records($start, $end),
            'routes' => $this->routePolylines($start, $end),
        ];
    }

    private function normalisedCompareMode(string $mode): string
    {
        return in_array($mode, self::COMPARE_MODES, true) ? $mode : 'previous-period';
    }

    /**
     * Per-day aggregates across the range (one query), the basis for every
     * series. Days with no activity are filled with zeros so buckets are dense.
     *
     * @return list<array{date: Carbon, sessions: int, secs: int, walk_m: int, run_m: int, ride_m: int, run_max_m: int}>
     */
    private function days(Carbon $start, Carbon $end): array
    {
        $rows = Activity::query()
            ->toBase()
            ->selectRaw('DATE(occurred_at) AS d')
            ->selectRaw('COUNT(*) AS sessions')
            ->selectRaw('COALESCE(SUM(duration), 0) AS secs')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = '".ActivityDiscipline::Walk->value."' THEN distance END), 0) AS walk_m")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = '".ActivityDiscipline::Run->value."' THEN distance END), 0) AS run_m")
            ->selectRaw("COALESCE(SUM(CASE WHEN type IN ('".ActivityDiscipline::Ride->value."', '".ActivityDiscipline::EbikeRide->value."') THEN distance END), 0) AS ride_m")
            ->selectRaw("COALESCE(MAX(CASE WHEN type = '".ActivityDiscipline::Run->value."' THEN distance END), 0) AS run_max_m")
            ->whereBetween('occurred_at', [$start, $end])
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $days = [];

        for ($cursor = $start->copy()->startOfDay(); $cursor <= $end; $cursor->addDay()) {
            $row = $rows->get($cursor->toDateString());

            $days[] = [
                'date' => $cursor->copy(),
                'sessions' => $row ? (int) $row->sessions : 0,
                'secs' => $row ? (int) $row->secs : 0,
                'walk_m' => $row ? (int) $row->walk_m : 0,
                'run_m' => $row ? (int) $row->run_m : 0,
                'ride_m' => $row ? (int) $row->ride_m : 0,
                'run_max_m' => $row ? (int) $row->run_max_m : 0,
            ];
        }

        return $days;
    }

    /**
     * Totals across a set of day rows.
     *
     * @param  list<array<string, mixed>>  $days
     * @return array{sessions: int, secs: int, walk_m: int, run_m: int, ride_m: int, run_max_m: int}
     */
    private function sumDays(array $days): array
    {
        return [
            'sessions' => (int) array_sum(array_column($days, 'sessions')),
            'secs' => (int) array_sum(array_column($days, 'secs')),
            'walk_m' => (int) array_sum(array_column($days, 'walk_m')),
            'run_m' => (int) array_sum(array_column($days, 'run_m')),
            'ride_m' => (int) array_sum(array_column($days, 'ride_m')),
            'run_max_m' => (int) max(array_column($days, 'run_max_m') ?: [0]),
        ];
    }

    /**
     * Which trend buckets, sparkline bucket and averaging unit suit the range
     * length, so the dashboard adapts from a week to many years.
     *
     * @return array{buckets: list<string>, default: string, spark: string, averageLabel: string, divisor: float}
     */
    private function plan(int $lenDays): array
    {
        $buckets = [];

        if ($lenDays <= 92) {
            $buckets[] = 'Day';
        }

        if ($lenDays <= 800) {
            $buckets[] = 'Week';
        }

        $buckets[] = 'Month';

        if ($lenDays > 366) {
            $buckets[] = 'Year';
        }

        $default = $lenDays <= 31 ? 'Day' : ($lenDays <= 182 ? 'Week' : 'Month');
        $spark = $default;

        if ($lenDays <= 45) {
            return ['buckets' => $buckets, 'default' => $default, 'spark' => $spark, 'averageLabel' => 'Daily average', 'divisor' => max(1, $lenDays)];
        }

        if ($lenDays <= 500) {
            return ['buckets' => $buckets, 'default' => $default, 'spark' => $spark, 'averageLabel' => 'Weekly average', 'divisor' => max(1, $lenDays / 7)];
        }

        return ['buckets' => $buckets, 'default' => $default, 'spark' => $spark, 'averageLabel' => 'Monthly average', 'divisor' => max(1, $lenDays / 30.44)];
    }

    /**
     * Aggregate the day rows into a labelled series at the given bucket unit.
     *
     * @param  list<array<string, mixed>>  $days
     * @return array{labels: list<string>, sessions: list<int>, secs: list<int>, walkMi: list<float>, runMi: list<float>, rideMi: list<float>, longestRunMi: list<float>}
     */
    private function series(array $days, string $unit, bool $multiYear): array
    {
        $groups = [];

        foreach ($days as $day) {
            [$key, $label] = $this->bucketKey($day['date'], $unit, $multiYear);

            $groups[$key] ??= ['label' => $label, 'sessions' => 0, 'secs' => 0, 'walk_m' => 0, 'run_m' => 0, 'ride_m' => 0, 'run_max_m' => 0];
            $groups[$key]['sessions'] += $day['sessions'];
            $groups[$key]['secs'] += $day['secs'];
            $groups[$key]['walk_m'] += $day['walk_m'];
            $groups[$key]['run_m'] += $day['run_m'];
            $groups[$key]['ride_m'] += $day['ride_m'];
            $groups[$key]['run_max_m'] = max($groups[$key]['run_max_m'], $day['run_max_m']);
        }

        $groups = array_values($groups);
        $mi = fn (int $metres): float => Distance::miles($metres, 1) ?? 0.0;

        return [
            'labels' => array_column($groups, 'label'),
            'sessions' => array_column($groups, 'sessions'),
            'secs' => array_column($groups, 'secs'),
            'walkMi' => array_map($mi, array_column($groups, 'walk_m')),
            'runMi' => array_map($mi, array_column($groups, 'run_m')),
            'rideMi' => array_map($mi, array_column($groups, 'ride_m')),
            'longestRunMi' => array_map($mi, array_column($groups, 'run_max_m')),
        ];
    }

    /**
     * The grouping key and display label for a date at a bucket unit.
     *
     * @return array{0: string, 1: string}
     */
    private function bucketKey(Carbon $date, string $unit, bool $multiYear): array
    {
        return match ($unit) {
            'Day' => [$date->format('Y-m-d'), $date->format('j M')],
            'Week' => [$date->format('o-W'), $date->format('j M')],
            'Year' => [$date->format('Y'), $date->format('Y')],
            default => [$date->format('Y-m'), $date->format($multiYear ? "M 'y" : 'M')],
        };
    }

    /**
     * Hero metric cards: value, unit, sparkline, and a percent delta versus the
     * comparison window (null when there's no comparison).
     *
     * @param  array<string, int>  $totals
     * @param  array<string, int>|null  $compare
     * @param  array<string, list<mixed>>  $spark
     * @return list<array<string, mixed>>
     */
    private function metrics(array $totals, ?array $compare, array $spark): array
    {
        $mi = fn (int $metres): float => Distance::miles($metres, 1) ?? 0.0;
        $hours = (int) round($totals['secs'] / 3600);
        $longest = $mi($totals['run_max_m']);

        $delta = function (float $now, ?float $was): ?int {
            if ($was === null || $was <= 0) {
                return null;
            }

            return (int) round((($now - $was) / $was) * 100);
        };

        return [
            ['label' => 'Sessions', 'value' => number_format($totals['sessions']), 'spark' => $spark['sessions'], 'delta' => $delta($totals['sessions'], $compare['sessions'] ?? null)],
            ['label' => 'Duration', 'value' => number_format($hours), 'unit' => 'h', 'spark' => array_map(fn (int $s): int => (int) round($s / 3600), $spark['secs']), 'delta' => $delta($totals['secs'], $compare['secs'] ?? null)],
            ['label' => 'Walked', 'distanceM' => $totals['walk_m'], 'precision' => 0, 'spark' => $spark['walkMi'], 'delta' => $delta($mi($totals['walk_m']), $compare ? $mi($compare['walk_m']) : null)],
            ['label' => 'Ran', 'distanceM' => $totals['run_m'], 'precision' => 0, 'spark' => $spark['runMi'], 'delta' => $delta($mi($totals['run_m']), $compare ? $mi($compare['run_m']) : null)],
            ['label' => 'Cycled', 'distanceM' => $totals['ride_m'], 'precision' => 0, 'spark' => $spark['rideMi'], 'delta' => $delta($mi($totals['ride_m']), $compare ? $mi($compare['ride_m']) : null)],
            ['label' => 'Longest run', 'distanceM' => $totals['run_max_m'], 'precision' => 1, 'spark' => $spark['longestRunMi'], 'delta' => $delta($longest, $compare ? $mi($compare['run_max_m']) : null)],
        ];
    }

    /**
     * Averages per the range's natural unit (day / week / month).
     *
     * @param  array<string, int>  $totals
     * @return list<array{label: string, display?: string, distanceM?: int, precision?: int}>
     */
    private function averages(array $totals, float $divisor): array
    {
        $distanceM = (int) round(($totals['walk_m'] + $totals['run_m'] + $totals['ride_m']) / $divisor);

        return [
            ['label' => 'Sessions', 'display' => (string) round($totals['sessions'] / $divisor, 1)],
            ['label' => 'Duration', 'display' => round(($totals['secs'] / 3600) / $divisor, 1).'h'],
            ['label' => 'Distance', 'distanceM' => $distanceM, 'precision' => 1],
        ];
    }

    /**
     * Duration over the range at each bucket the plan allows.
     *
     * @param  list<array<string, mixed>>  $days
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function trend(array $days, array $plan, bool $multiYear): array
    {
        $hours = fn (int $seconds): float => round($seconds / 3600, 1);

        $buckets = array_map(function (string $unit) use ($days, $multiYear, $hours): array {
            $series = $this->series($days, $unit, $multiYear);

            return ['label' => $unit, 'labels' => $series['labels'], 'values' => array_map($hours, $series['secs'])];
        }, $plan['buckets']);

        return ['metric' => 'Duration', 'unit' => 'h', 'default' => $plan['default'], 'buckets' => $buckets];
    }

    /**
     * Every activity type by session count for the range, sorted.
     *
     * @return list<array{label: string, value: int}>
     */
    private function byType(Carbon $start, Carbon $end): array
    {
        return Activity::query()
            ->toBase()
            ->selectRaw('type, COUNT(*) AS total')
            ->whereBetween('occurred_at', [$start, $end])
            ->groupBy('type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => ['label' => Str::headline((string) $row->type), 'value' => (int) $row->total])
            ->all();
    }

    /**
     * Activity counts by weekday (0=Mon) and hour for the range.
     *
     * @return array{grid: list<list<int>>, max: int}
     */
    private function busiest(Carbon $start, Carbon $end): array
    {
        $grid = array_fill(0, 7, array_fill(0, 24, 0));

        $rows = Activity::query()
            ->toBase()
            ->selectRaw("CAST(strftime('%w', occurred_at) AS INTEGER) AS dow, CAST(strftime('%H', occurred_at) AS INTEGER) AS hr, COUNT(*) AS total")
            ->whereBetween('occurred_at', [$start, $end])
            ->groupByRaw("strftime('%w', occurred_at), strftime('%H', occurred_at)")
            ->get();

        $max = 0;

        foreach ($rows as $row) {
            $day = ((int) $row->dow + 6) % 7;
            $total = (int) $row->total;
            $grid[$day][(int) $row->hr] = $total;
            $max = max($max, $total);
        }

        return ['grid' => $grid, 'max' => $max];
    }

    /**
     * Personal bests within the range.
     *
     * @return list<array{label: string, value?: string, distanceM?: int, precision?: int}>
     */
    private function records(Carbon $start, Carbon $end): array
    {
        $between = fn ($query) => $query->whereBetween('occurred_at', [$start, $end]);
        $longestRun = (int) $between(Activity::query()->where('type', ActivityDiscipline::Run->value))->max('distance');
        $longestRide = (int) $between(Activity::query()->whereIn('type', [ActivityDiscipline::Ride->value, ActivityDiscipline::EbikeRide->value]))->max('distance');
        $longestSession = (int) $between(Activity::query())->max('duration');

        return [
            ['label' => 'Longest run', 'distanceM' => $longestRun, 'precision' => 1],
            ['label' => 'Longest ride', 'distanceM' => $longestRide, 'precision' => 1],
            ['label' => 'Longest session', 'value' => $longestSession > 0 ? Carbon::now()->subSeconds($longestSession)->diffForHumans(Carbon::now(), ['parts' => 2, 'short' => true, 'syntax' => Carbon::DIFF_ABSOLUTE]) : '0m'],
        ];
    }

    /**
     * Encoded polylines from the range's activities, for the route map.
     *
     * @return list<string>
     */
    private function routePolylines(Carbon $start, Carbon $end): array
    {
        return Activity::query()
            ->whereRaw("json_extract(meta, '$.polyline') IS NOT NULL")
            ->whereBetween('occurred_at', [$start, $end])
            ->latest('occurred_at')
            ->limit(self::ROUTE_LIMIT)
            ->get(['meta'])
            ->map(fn (Activity $activity): ?string => $activity->meta['polyline'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The comparison window for the mode, or null when comparison is off.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function comparisonWindow(string $mode, Carbon $start, Carbon $end): ?array
    {
        if ($mode === 'none') {
            return null;
        }

        if ($mode === 'previous-year') {
            return [$start->copy()->subYear(), $end->copy()->subYear()];
        }

        // previous-period: the same-length window immediately before the range.
        $lenDays = $start->diffInDays($end);
        $comparisonEnd = $start->copy()->subDay()->endOfDay();

        return [$comparisonEnd->copy()->subDays($lenDays)->startOfDay(), $comparisonEnd];
    }

    private function compareLabel(string $mode): ?string
    {
        return match ($mode) {
            'previous-year' => 'previous year',
            'none' => null,
            default => 'previous period',
        };
    }

    private function rangeLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->format('j M Y');
        }

        if ($start->year === $end->year) {
            return $start->format('j M').' - '.$end->format('j M Y');
        }

        return $start->format('j M Y').' - '.$end->format('j M Y');
    }
}
