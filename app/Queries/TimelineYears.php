<?php

namespace App\Queries;

use App\Models\TimelineEntry;
use App\Support\SqlDate;
use Illuminate\Support\Facades\Cache;

/**
 * Every year the timeline holds something in, newest first, for the jump
 * control under the feed.
 *
 * Cached until midnight: the set only grows once a year, and it is read on
 * every page of a feed that runs to hundreds of them.
 */
final class TimelineYears
{
    private const KEY = 'timeline.years';

    /** @return array<int, array{year: int, href: string}> */
    public function __invoke(): array
    {
        return Cache::remember(self::KEY, now()->endOfDay(), function (): array {
            $year = SqlDate::year('occurred_at');

            return TimelineEntry::query()
                ->toBase()
                ->selectRaw("{$year} as year")
                ->groupBy('year')
                ->orderByDesc('year')
                ->pluck('year')
                ->map(fn (int|string $value): array => [
                    'year' => (int) $value,
                    'href' => '/'.$value,
                ])
                ->all();
        });
    }

    /** Drop the cached list, so the next read recomputes it. */
    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
