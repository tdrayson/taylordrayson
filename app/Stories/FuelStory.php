<?php

namespace App\Stories;

use App\Models\Fuel;
use App\Support\OgMeta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes the full set of figures and chart series behind the fuel data story
 * from live fill-up records, so the narrative stays current on every refresh.
 *
 * MPG is derived per fill from the odometer delta since the previous fill,
 * converted with the Imperial gallon (4.546 L). Since the tank is always filled
 * to full, litres bought equals litres burned, making the method sound.
 */
class FuelStory implements Story
{
    private const LITRES_PER_GALLON = 4.546;

    /** Ignore implausible odometer deltas (missed/duplicate readings). */
    private const MAX_PLAUSIBLE_MILES = 900;

    public function slug(): string
    {
        return 'fuel';
    }

    public function component(): string
    {
        return 'Stories/Fuel';
    }

    /**
     * @return array<string, mixed>
     */
    public function og(): array
    {
        return OgMeta::fuelStory();
    }

    /**
     * @return array{slug: string, type: string, title: string, description: string, accent: string}
     */
    public function card(): array
    {
        $og = $this->og();

        return [
            'slug' => $this->slug(),
            'type' => 'fuel',
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
        $fills = Fuel::query()->orderBy('occurred_at')->get();

        if ($fills->isEmpty()) {
            return ['hasData' => false];
        }

        $legs = $this->legs($fills);
        $byYear = $this->byYear($fills, $legs);

        return [
            'hasData' => true,
            'car' => $this->car($fills),
            'kpis' => $this->kpis($fills, $legs),
            'price' => $this->price($fills),
            'byYear' => $byYear->values()->all(),
            'mpgByMonth' => $this->mpgByMonth($legs),
            'seasonal' => $this->seasonal($legs),
            'gaps' => $this->gaps($fills),
            'intervals' => $this->intervals($fills),
            'fuelCard' => $this->fuelCard($fills),
            'pencePerMile' => $this->pencePerMile($byYear),
            'projection' => $this->projection($fills, $byYear),
        ];
    }

    /**
     * Per-leg distance + MPG, derived from consecutive odometer readings.
     *
     * @return Collection<int, array{date: Carbon, year: int, month: int, miles: int, litres: float, mpg: float}>
     */
    private function legs(Collection $fills): Collection
    {
        $legs = collect();
        $previous = null;

        foreach ($fills as $fill) {
            if ($previous && $fill->odometer && $previous->odometer && $fill->litres > 0) {
                $miles = (int) ($fill->odometer - $previous->odometer);

                if ($miles > 0 && $miles < self::MAX_PLAUSIBLE_MILES) {
                    $legs->push([
                        'date' => $fill->occurred_at,
                        'year' => $fill->occurred_at->year,
                        'month' => $fill->occurred_at->month,
                        'miles' => $miles,
                        'litres' => (float) $fill->litres,
                        'mpg' => $miles / ($fill->litres / self::LITRES_PER_GALLON),
                    ]);
                }
            }

            $previous = $fill;
        }

        return $legs;
    }

    /**
     * @return array{name: string, plate: string, since: string}
     */
    private function car(Collection $fills): array
    {
        $vehicle = $fills->first()->vehicle ?? [];
        $plate = (string) $fills->first()->vehicle_id;

        return [
            'name' => trim(($vehicle['make'] ?? '').' '.($vehicle['model'] ?? '')) ?: 'My car',
            'plate' => strtoupper($plate),
            'since' => $fills->first()->occurred_at->format('F Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kpis(Collection $fills, Collection $legs): array
    {
        $miles = (int) ($fills->whereNotNull('odometer')->max('odometer') - $fills->whereNotNull('odometer')->min('odometer'));

        return [
            'fills' => $fills->count(),
            'fromYear' => $fills->first()->occurred_at->year,
            'toYear' => $fills->last()->occurred_at->year,
            'fromLabel' => $fills->first()->occurred_at->format('F Y'),
            'toLabel' => $fills->last()->occurred_at->format('F Y'),
            'updated' => $fills->last()->occurred_at->format('j F Y'),
            'odometer' => (int) $fills->whereNotNull('odometer')->max('odometer'),
            'miles' => $miles,
            // A relatable yardstick: laps of the Earth (circumference 24,901 mi).
            'aroundEarth' => round($miles / 24901, 1),
            'litres' => (int) round($fills->sum('litres')),
            'spend' => round($fills->sum('cost'), 2),
            'avgMpg' => round($legs->avg('mpg') ?? 0, 1),
            'avgPrice' => round($fills->whereNotNull('price_per_litre')->where('price_per_litre', '>', 0)->avg('price_per_litre'), 3),
        ];
    }

    /**
     * Cheapest and dearest litre, the swing between them, and a price-per-fill
     * series for the line chart.
     *
     * @return array<string, mixed>
     */
    private function price(Collection $fills): array
    {
        $priced = $fills->whereNotNull('price_per_litre')->where('price_per_litre', '>', 0);
        $low = $priced->sortBy('price_per_litre')->first();
        $high = $priced->sortByDesc('price_per_litre')->first();

        return [
            'low' => ['value' => round($low->price_per_litre, 3), 'when' => $low->occurred_at->format('F Y'), 'date' => $low->occurred_at->format('Y-m-d')],
            'high' => ['value' => round($high->price_per_litre, 3), 'when' => $high->occurred_at->format('F Y'), 'date' => $high->occurred_at->format('Y-m-d')],
            'swingPct' => (int) round(($high->price_per_litre / $low->price_per_litre - 1) * 100),
            'series' => $priced->map(fn (Fuel $fill): array => [
                'date' => $fill->occurred_at->format('Y-m-d'),
                'price' => round($fill->price_per_litre, 3),
            ])->values()->all(),
        ];
    }

    /**
     * Per-year totals and averages.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function byYear(Collection $fills, Collection $legs): Collection
    {
        $legsByYear = $legs->groupBy('year');

        return $fills->groupBy(fn (Fuel $fill): int => $fill->occurred_at->year)
            ->map(function (Collection $yearFills, int $year) use ($legsByYear): array {
                $yearLegs = $legsByYear->get($year, collect());
                $miles = (int) $yearLegs->sum('miles');
                $cost = round($yearFills->sum('cost'), 2);
                $priced = $yearFills->whereNotNull('price_per_litre')->where('price_per_litre', '>', 0);

                return [
                    'year' => $year,
                    'fills' => $yearFills->count(),
                    'miles' => $miles,
                    'cost' => $cost,
                    'litres' => (int) round($yearFills->sum('litres')),
                    'avgPrice' => round($priced->avg('price_per_litre') ?? 0, 3),
                    'avgMpg' => round($yearLegs->avg('mpg') ?? 0, 1),
                    'pencePerMile' => $miles > 0 ? round($cost / $miles * 100, 1) : null,
                ];
            });
    }

    /**
     * Average MPG by calendar month (1-12), exposing the seasonal swing.
     *
     * @return array<int, array{month: int, mpg: float}>
     */
    private function mpgByMonth(Collection $legs): array
    {
        $byMonth = $legs->groupBy('month');

        return collect(range(1, 12))
            ->map(fn (int $month): array => [
                'month' => $month,
                'mpg' => round($byMonth->get($month, collect())->avg('mpg') ?? 0, 1),
            ])
            ->all();
    }

    /**
     * @return array{summer: float, winter: float, diff: float}
     */
    private function seasonal(Collection $legs): array
    {
        $summer = round($legs->whereIn('month', [6, 7, 8])->avg('mpg') ?? 0, 1);
        $winter = round($legs->whereIn('month', [12, 1, 2])->avg('mpg') ?? 0, 1);

        return ['summer' => $summer, 'winter' => $winter, 'diff' => round($summer - $winter, 1)];
    }

    /**
     * The longest gaps between fills, where the car sat largely unused.
     *
     * @return array<int, array{days: int, from: string, to: string}>
     */
    private function gaps(Collection $fills): array
    {
        $gaps = [];
        $previous = null;

        foreach ($fills as $fill) {
            if ($previous) {
                $gaps[] = [
                    'days' => (int) $previous->occurred_at->diffInDays($fill->occurred_at),
                    'from' => $previous->occurred_at->format('j F Y'),
                    'to' => $fill->occurred_at->format('j F Y'),
                ];
            }

            $previous = $fill;
        }

        usort($gaps, fn (array $a, array $b): int => $b['days'] <=> $a['days']);

        return array_slice($gaps, 0, 4);
    }

    /**
     * Spacing between fills: the longest and shortest gaps plus the typical
     * (median) interval, so the rhythm of filling up is shown, not just told.
     *
     * @return array{longest: int, shortest: int, typical: int}|array{}
     */
    private function intervals(Collection $fills): array
    {
        $days = [];
        $previous = null;

        foreach ($fills as $fill) {
            if ($previous) {
                $days[] = (int) $previous->occurred_at->diffInDays($fill->occurred_at);
            }

            $previous = $fill;
        }

        if ($days === []) {
            return [];
        }

        sort($days);
        $count = count($days);
        $mid = intdiv($count, 2);
        $median = $count % 2 === 1 ? $days[$mid] : intdiv($days[$mid - 1] + $days[$mid], 2);

        return ['longest' => max($days), 'shortest' => min($days), 'typical' => $median];
    }

    /**
     * Fuel-card savings: pump cost versus the discounted card cost, plus the
     * running cumulative total so the saving can be charted as it builds.
     *
     * @return array{fills: int, since: ?string, saved: float, best: ?array<string, mixed>, cumulative: list<array{date: string, saved: float}>}
     */
    private function fuelCard(Collection $fills): array
    {
        $card = $fills->whereNotNull('fuel_card_cost');
        $best = $card->sortByDesc(fn (Fuel $fill): float => $fill->cost - $fill->fuel_card_cost)->first();

        $running = 0.0;
        $cumulative = $card->sortBy('occurred_at')->map(function (Fuel $fill) use (&$running): array {
            $running += $fill->cost - $fill->fuel_card_cost;

            return [
                'date' => $fill->occurred_at->format('Y-m-d'),
                'saved' => round($running, 2),
            ];
        })->values()->all();

        return [
            'fills' => $card->count(),
            'since' => $card->isNotEmpty() ? $card->sortBy('occurred_at')->first()->occurred_at->format('F Y') : null,
            'saved' => round($card->sum(fn (Fuel $fill): float => $fill->cost - $fill->fuel_card_cost), 2),
            'best' => $best ? [
                'amount' => round($best->cost - $best->fuel_card_cost, 2),
                'when' => $best->occurred_at->format('j F Y'),
                'date' => $best->occurred_at->format('Y-m-d'),
                'pump' => round($best->cost, 2),
                'card' => round($best->fuel_card_cost, 2),
            ] : null,
            'cumulative' => $cumulative,
        ];
    }

    /**
     * Project the odometer to its next 5,000-mile milestone using the recent
     * annual mileage, so the closing looks forward from live data.
     *
     * @param  Collection<int, array<string, mixed>>  $byYear
     * @return array{milestone: int, eta: string, annual: int}|array{}
     */
    private function projection(Collection $fills, Collection $byYear): array
    {
        $odometer = (int) $fills->whereNotNull('odometer')->max('odometer');
        $complete = $byYear
            ->filter(fn (array $year): bool => $year['year'] > $fills->first()->occurred_at->year && $year['year'] < $fills->last()->occurred_at->year)
            ->values();
        $annual = $complete->slice(-3)->avg('miles') ?? $complete->avg('miles');

        if (! $annual || $annual <= 0 || $odometer <= 0) {
            return [];
        }

        $milestone = (int) (ceil(($odometer + 1) / 5000) * 5000);
        $eta = $fills->last()->occurred_at->copy()->addDays((int) round(($milestone - $odometer) / $annual * 365));

        return ['milestone' => $milestone, 'eta' => $eta->format('F Y'), 'annual' => (int) round($annual)];
    }

    /**
     * Pence-per-mile: the earliest full year as a pre-crisis baseline against the
     * most recent, since this folds price, mileage and efficiency into one number.
     *
     * @param  Collection<int, array<string, mixed>>  $byYear
     * @return array{baseline: array<string, mixed>, latest: array<string, mixed>, changePct: ?int}
     */
    private function pencePerMile(Collection $byYear): array
    {
        $withPpm = $byYear->filter(fn (array $year): bool => $year['pencePerMile'] !== null && $year['fills'] >= 8)->values();
        $baseline = $withPpm->first();
        $latest = $withPpm->last();

        $changePct = $baseline && $latest && $baseline['pencePerMile'] > 0
            ? (int) round(($latest['pencePerMile'] / $baseline['pencePerMile'] - 1) * 100)
            : null;

        return [
            'baseline' => $baseline ? ['year' => $baseline['year'], 'value' => $baseline['pencePerMile']] : [],
            'latest' => $latest ? ['year' => $latest['year'], 'value' => $latest['pencePerMile']] : [],
            'changePct' => $changePct,
        ];
    }
}
