<?php

namespace App\Links\Resolvers;

use App\Data\LinkPreviewData;
use App\Links\LinkResolver;
use App\Models\TimelineEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A dated archive: /2026, /2026/05 or /2026/05/12.
 */
class PeriodResolver implements LinkResolver
{
    public function resolve(string $path): ?LinkPreviewData
    {
        if (preg_match('#^/(\d{4})(?:/(\d{2}))?(?:/(\d{2}))?$#', $path, $matches) !== 1) {
            return null;
        }

        [$year, $month, $day] = [$matches[1], $matches[2] ?? null, $matches[3] ?? null];

        $date = Carbon::createFromDate((int) $year, (int) ($month ?? 1), (int) ($day ?? 1));

        if ($month !== null && ((int) $month < 1 || (int) $month > 12)) {
            return null;
        }

        [$start, $end, $label] = match (true) {
            $day !== null => [$date->copy()->startOfDay(), $date->copy()->endOfDay(), $date->format('j F Y')],
            $month !== null => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth(), $date->format('F Y')],
            default => [$date->copy()->startOfYear(), $date->copy()->endOfYear(), $year],
        };

        $count = TimelineEntry::query()->whereBetween('occurred_at', [$start, $end])->count();

        if ($count === 0) {
            return null;
        }

        return LinkPreviewData::period($path, $label, number_format($count).' '.Str::plural('entry', $count));
    }
}
