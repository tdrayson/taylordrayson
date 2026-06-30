<?php

namespace App\Stories;

use App\Models\Calorie;
use App\Support\OgMeta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Computes the figures and chart series behind the food data story from live
 * calorie logs. The log is many items per day, so most figures roll the items
 * up into per-day totals first, then per-year from there.
 */
class FoodStory implements Story
{
    /** A "low" day: well under normal intake, the fingerprint of an illness flare. */
    private const LOW_DAY_KCAL = 1200;

    /** Window (days) for the rolling crash/rebound averages around the surgery. */
    private const ROLL_DAYS = 30;

    /** Common fizzy soft-drink labels (word-boundary matched so "cola" doesn't
     * catch "chocolate"); energy drinks like Monster and Lucozade are excluded. */
    private const FIZZY_PATTERN = '/\b(coke|cola|pepsi|fanta|sprite|7\s?up|dr\.?\s?pepper|tango|lilt|irn[\s-]?bru|lemonade|mountain\s?dew|soda|ginger\s?(?:beer|ale))\b/i';

    public function slug(): string
    {
        return 'food';
    }

    public function component(): string
    {
        return 'Stories/Food';
    }

    /**
     * @return array<string, mixed>
     */
    public function og(): array
    {
        return OgMeta::foodStory();
    }

    /**
     * @return array{slug: string, type: string, title: string, description: string, accent: string}
     */
    public function card(): array
    {
        $og = $this->og();

        return [
            'slug' => $this->slug(),
            'type' => 'calorie',
            'title' => $og['heading'],
            'description' => $og['description'],
            'accent' => $og['accent'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $items = Calorie::query()->orderBy('occurred_at')->get();

        if ($items->isEmpty()) {
            return ['hasData' => false];
        }

        $byDay = $this->byDay($items);
        $byYear = $this->byYear($byDay);

        return [
            'hasData' => true,
            'kpis' => $this->kpis($items, $byDay),
            'byYear' => $byYear->values()->all(),
            'streak' => $this->streak($byDay),
            'distribution' => $this->distribution($byDay),
            'macros' => $this->macros($items),
            'protein' => $this->protein($byYear),
            'fizzy' => $this->fizzy($items, $byDay->first()['year'], $byDay->last()['year']),
            'meals' => $this->meals($items),
            'topFoods' => $this->topFoods($items),
            'seasonal' => $this->seasonal($byDay),
            'surgery' => $this->surgery($byDay),
        ];
    }

    /**
     * Roll the per-item rows up into one total per calendar day.
     *
     * @return Collection<int, array{date: string, year: int, month: int, kcal: int, protein: float, items: int}>
     */
    private function byDay(Collection $items): Collection
    {
        return $items
            ->groupBy(fn (Calorie $item): string => $item->occurred_at->toDateString())
            ->map(fn (Collection $day, string $date): array => [
                'date' => $date,
                'year' => (int) substr($date, 0, 4),
                'month' => (int) substr($date, 5, 2),
                'kcal' => (int) $day->sum('calories'),
                'protein' => (float) $day->sum('protein'),
                'items' => $day->count(),
            ])
            ->sortKeys()
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function kpis(Collection $items, Collection $byDay): array
    {
        $days = $byDay->count();
        $totalKcal = (int) $items->sum('calories');
        $first = $byDay->first();
        $last = $byDay->last();

        return [
            'days' => $days,
            'items' => $items->count(),
            'totalKcal' => $totalKcal,
            'avgPerDay' => $days > 0 ? (int) round($totalKcal / $days) : 0,
            'avgItems' => $days > 0 ? round($items->count() / $days, 1) : 0,
            'fromYear' => $first['year'],
            'toYear' => $last['year'],
            'fromLabel' => Carbon::parse($first['date'])->format('F Y'),
            'toLabel' => Carbon::parse($last['date'])->format('F Y'),
            'updated' => Carbon::parse($last['date'])->format('j F Y'),
        ];
    }

    /**
     * The longest unbroken run of consecutive logged days.
     *
     * @return array{days: int, start: ?string, end: ?string}
     */
    private function streak(Collection $byDay): array
    {
        $longest = 0;
        $run = 0;
        $previous = null;
        $start = null;
        $longestStart = null;
        $longestEnd = null;

        foreach ($byDay as $day) {
            if ($previous instanceof Carbon && $previous->copy()->addDay()->toDateString() === $day['date']) {
                $run++;
            } else {
                $run = 1;
                $start = $day['date'];
            }

            if ($run > $longest) {
                $longest = $run;
                $longestStart = $start;
                $longestEnd = $day['date'];
            }

            $previous = Carbon::parse($day['date']);
        }

        return [
            'days' => $longest,
            'start' => $longestStart ? Carbon::parse($longestStart)->format('j F Y') : null,
            'end' => $longestEnd ? Carbon::parse($longestEnd)->format('j F Y') : null,
        ];
    }

    /**
     * The spread of a day's intake: average, median, lightest and heaviest.
     *
     * @return array<string, mixed>
     */
    private function distribution(Collection $byDay): array
    {
        $kcals = $byDay->pluck('kcal')->sort()->values();
        $count = $kcals->count();
        $mid = intdiv($count, 2);
        $median = $count === 0 ? 0 : ($count % 2 === 1 ? $kcals[$mid] : intdiv($kcals[$mid - 1] + $kcals[$mid], 2));
        $low = $byDay->sortBy('kcal')->first();
        $high = $byDay->sortByDesc('kcal')->first();

        return [
            'avg' => $count > 0 ? (int) round($kcals->avg()) : 0,
            'median' => $median,
            'low' => ['kcal' => $low['kcal'], 'when' => Carbon::parse($low['date'])->format('j F Y'), 'date' => $low['date']],
            'high' => ['kcal' => $high['kcal'], 'when' => Carbon::parse($high['date'])->format('j F Y'), 'date' => $high['date']],
        ];
    }

    /**
     * All-time macro split, calorie-weighted (protein/carbs 4 kcal/g, fat 9).
     *
     * @return array<string, array{grams: int, pct: int}>
     */
    private function macros(Collection $items): array
    {
        $protein = (float) $items->sum('protein');
        $carbs = (float) $items->sum('carbs');
        $fat = (float) $items->sum('fat');
        $proteinKcal = $protein * 4;
        $carbsKcal = $carbs * 4;
        $fatKcal = $fat * 9;
        $total = $proteinKcal + $carbsKcal + $fatKcal;

        $pct = fn (float $part): int => $total > 0 ? (int) round($part / $total * 100) : 0;

        return [
            'protein' => ['grams' => (int) round($protein), 'pct' => $pct($proteinKcal)],
            'carbs' => ['grams' => (int) round($carbs), 'pct' => $pct($carbsKcal)],
            'fat' => ['grams' => (int) round($fat), 'pct' => $pct($fatKcal)],
        ];
    }

    /**
     * Per-year averages plus a count of low (flare) days.
     *
     * @return Collection<int, array{year: int, days: int, avgKcal: int, avgProtein: int, lowDays: int}>
     */
    private function byYear(Collection $byDay): Collection
    {
        return $byDay
            ->groupBy('year')
            ->map(function (Collection $days, int $year): array {
                $count = $days->count();

                return [
                    'year' => $year,
                    'days' => $count,
                    'avgKcal' => $count > 0 ? (int) round($days->avg('kcal')) : 0,
                    'avgProtein' => $count > 0 ? (int) round($days->avg('protein')) : 0,
                    'lowDays' => $days->filter(fn (array $day): bool => $day['kcal'] < self::LOW_DAY_KCAL)->count(),
                ];
            })
            ->sortKeys()
            ->values();
    }

    /**
     * Protein per day in the first vs most recent well-tracked year.
     *
     * @param  Collection<int, array<string, mixed>>  $byYear
     * @return array{baseline: array<string, mixed>, latest: array<string, mixed>}
     */
    private function protein(Collection $byYear): array
    {
        $full = $byYear->filter(fn (array $year): bool => $year['days'] >= 300)->values();
        $baseline = $full->first();
        $latest = $full->last();

        return [
            'baseline' => $baseline ? ['year' => $baseline['year'], 'value' => $baseline['avgProtein']] : [],
            'latest' => $latest ? ['year' => $latest['year'], 'value' => $latest['avgProtein']] : [],
        ];
    }

    /**
     * Fizzy soft drinks logged per year, the clearest trace of the habit I kicked.
     *
     * @return array<string, mixed>
     */
    private function fizzy(Collection $items, int $fromYear, int $toYear): array
    {
        $fizzy = $items->filter(fn (Calorie $item): bool => preg_match(self::FIZZY_PATTERN, (string) $item->name) === 1);
        $counts = $fizzy->groupBy(fn (Calorie $item): int => $item->occurred_at->year)->map->count();

        $series = collect(range($fromYear, $toYear))
            ->map(fn (int $year): array => ['year' => $year, 'count' => (int) ($counts[$year] ?? 0)])
            ->all();

        $peakYear = $counts->isNotEmpty() ? (int) $counts->sortDesc()->keys()->first() : null;

        return [
            'total' => $fizzy->count(),
            'series' => $series,
            'peak' => $peakYear !== null ? ['year' => $peakYear, 'count' => (int) $counts[$peakYear]] : [],
            'latest' => ['year' => $toYear, 'count' => (int) ($counts[$toYear] ?? 0)],
        ];
    }

    /**
     * Share of calories by meal of the day.
     *
     * @return array<int, array{meal: string, kcal: int, pct: float}>
     */
    private function meals(Collection $items): array
    {
        $total = (int) $items->sum('calories');
        $byMeal = $items->groupBy(fn (Calorie $item): string => strtolower((string) $item->meal));

        return collect(['breakfast', 'lunch', 'dinner', 'snacks'])
            ->map(function (string $meal) use ($byMeal, $total): array {
                $kcal = (int) $byMeal->get($meal, collect())->sum('calories');

                return [
                    'meal' => $meal,
                    'kcal' => $kcal,
                    'pct' => $total > 0 ? round($kcal / $total * 100, 1) : 0,
                ];
            })
            ->all();
    }

    /**
     * The most-logged foods, fuzzily grouped: every coffee (cappuccino, latte,
     * and friends) and every Coke (Can Of Coke, Diet Coke, ...) collapse into
     * one apiece, and the verbose export names ("Milk, Semi-Skimmed, ...") group
     * on their head noun, so the list reflects what I actually keep eating.
     *
     * @return array<int, array{name: string, count: int}>
     */
    private function topFoods(Collection $items): array
    {
        $coffee = ['coffee', 'cappuccino', 'latte', 'americano', 'espresso', 'flat white', 'macchiato', 'mocha', 'cortado'];
        $counts = [];

        foreach ($items as $item) {
            $name = trim((string) $item->name);
            $lower = strtolower($name);

            if (Str::contains($lower, $coffee)) {
                $label = 'Coffee';
            } elseif (str_contains($lower, 'coke')) {
                $label = 'Coke';
            } else {
                $label = trim(explode(',', $name)[0]);
            }

            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        arsort($counts);

        return collect($counts)
            ->take(8)
            ->map(fn (int $count, string $name): array => ['name' => $name, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * Average intake by calendar month (1-12), to expose any seasonal swing.
     *
     * @return array<int, array{month: int, avgKcal: int}>
     */
    private function seasonal(Collection $byDay): array
    {
        $byMonth = $byDay->groupBy('month');

        return collect(range(1, 12))
            ->map(fn (int $month): array => [
                'month' => $month,
                'avgKcal' => (int) round($byMonth->get($month, collect())->avg('kcal') ?? 0),
            ])
            ->all();
    }

    /**
     * The surgery window: a daily series for the chart, plus the lowest and
     * highest rolling 30-day averages (the crash and the rebound). The log is
     * continuous, so a 30-row slice is 30 consecutive days.
     *
     * @return array<string, mixed>
     */
    private function surgery(Collection $byDay): array
    {
        $series = $byDay
            ->filter(fn (array $day): bool => $day['date'] >= '2022-12-01' && $day['date'] <= '2023-03-31')
            ->map(fn (array $day): array => ['date' => $day['date'], 'kcal' => $day['kcal']])
            ->values()
            ->all();

        $kcals = $byDay->pluck('kcal')->values()->all();
        $dates = $byDay->pluck('date')->values()->all();
        $low = null;
        $high = null;

        for ($i = self::ROLL_DAYS - 1, $n = count($kcals); $i < $n; $i++) {
            $avg = array_sum(array_slice($kcals, $i - self::ROLL_DAYS + 1, self::ROLL_DAYS)) / self::ROLL_DAYS;
            $window = ['avg' => (int) round($avg), 'from' => $dates[$i - self::ROLL_DAYS + 1], 'to' => $dates[$i]];

            if ($low === null || $avg < $low['raw']) {
                $low = $window + ['raw' => $avg];
            }

            if ($high === null || $avg > $high['raw']) {
                $high = $window + ['raw' => $avg];
            }
        }

        return [
            'series' => $series,
            'crash' => $this->formatWindow($low),
            'rebound' => $this->formatWindow($high),
        ];
    }

    /**
     * @param  array{avg: int, from: string, to: string}|null  $window
     * @return array<string, mixed>
     */
    private function formatWindow(?array $window): array
    {
        if ($window === null) {
            return [];
        }

        return [
            'avg' => $window['avg'],
            'from' => Carbon::parse($window['from'])->format('j F Y'),
            'to' => Carbon::parse($window['to'])->format('j F Y'),
        ];
    }
}
