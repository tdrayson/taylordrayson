<?php

namespace App\Queries;

use App\Enums\Cadence;
use App\Models\TimelineEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Current and longest streaks for every timeline type at every cadence, in
 * one pass. Cached until midnight, because `longest` needs a full table scan.
 */
final class StreakDays
{
    private const KEY = 'streaks.all';

    /**
     * @return array<string, array<string, array{current: int, longest: int}>>
     */
    public function __invoke(): array
    {
        return Cache::remember(self::KEY, now()->endOfDay(), fn (): array => $this->compute());
    }

    /** Drop the cached streaks, so the next read recomputes them. */
    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }

    /**
     * @return array<string, array<string, array{current: int, longest: int}>>
     */
    private function compute(): array
    {
        $rows = TimelineEntry::query()
            ->toBase()
            ->select('timelineable_type', 'occurred_at')
            ->orderBy('occurred_at')
            ->get();

        $streaks = [];

        foreach ($rows->groupBy('timelineable_type') as $type => $entries) {
            foreach (Cadence::cases() as $cadence) {
                $buckets = $entries
                    ->map(fn ($row): int => $cadence->index(CarbonImmutable::parse($row->occurred_at)))
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $streaks[$type][$cadence->value] = $this->runs($buckets, $cadence);
            }
        }

        return $streaks;
    }

    /**
     * The current run only counts if its last bucket is today's or
     * yesterday's, so an empty today does not reset it before it is judged.
     *
     * @param  list<int>  $buckets
     * @return array{current: int, longest: int}
     */
    private function runs(array $buckets, Cadence $cadence): array
    {
        $longest = 0;
        $run = 0;
        $previous = null;
        $endsAt = null;

        foreach ($buckets as $bucket) {
            $run = ($previous !== null && $bucket === $previous + 1) ? $run + 1 : 1;
            $longest = max($longest, $run);
            $previous = $bucket;
            $endsAt = $bucket;
        }

        $now = $cadence->index(CarbonImmutable::now());
        $current = ($endsAt === $now || $endsAt === $now - 1) ? $run : 0;

        return ['current' => $current, 'longest' => $longest];
    }
}
