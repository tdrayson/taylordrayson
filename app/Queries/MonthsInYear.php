<?php

namespace App\Queries;

use App\Models\TimelineEntry;
use App\Support\SqlDate;
use Illuminate\Support\Carbon;

/**
 * The twelve months of a year with how much each holds, for the strip that
 * sits under a year archive's header.
 *
 * Every month is present even at zero: a gap is worth showing as a gap, and a
 * month with nothing in it renders as plain text rather than a dead link.
 */
final class MonthsInYear
{
    /** @return list<array{month: int, label: string, href: string, total: int}> */
    public function __invoke(int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = (clone $start)->endOfYear()->endOfDay();

        $totals = TimelineEntry::query()
            ->toBase()
            ->selectRaw(SqlDate::month('occurred_at').' as month, count(*) as total')
            ->whereBetween('occurred_at', [$start, $end])
            ->groupBy('month')
            ->pluck('total', 'month');

        return collect(range(1, 12))
            ->map(fn (int $month): array => [
                'month' => $month,
                'label' => Carbon::create($year, $month, 1)->format('M'),
                'href' => sprintf('/%d/%02d', $year, $month),
                'total' => (int) ($totals[$month] ?? $totals[(string) $month] ?? 0),
            ])
            ->all();
    }
}
