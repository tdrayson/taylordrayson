<?php

namespace App\Queries;

use App\Enums\Cadence;
use App\Models\TimelineEntry;
use App\Support\EntryInstant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        // Local end of day, not now()->endOfDay(): app.timezone is UTC, so the
        // latter expires an hour into the local day during BST.
        return Cache::remember(self::KEY, EntryInstant::nowLocal()->endOfDay(), fn (): array => $this->compute());
    }

    /**
     * One type's streaks at one cadence, zeros when it has none. The sidebar and
     * the streak tags both read through here, so they cannot disagree.
     *
     * @param  class-string  $model
     * @return array{current: int, longest: int}
     */
    public function for(string $model, Cadence $cadence): array
    {
        return $this()[Relation::getMorphAlias($model)][$cadence->value] ?? ['current' => 0, 'longest' => 0];
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
            ->select('dataset', 'occurred_at')
            ->orderBy('occurred_at')
            ->get();

        $streaks = [];

        foreach ($rows->groupBy('dataset') as $type => $entries) {
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
     * The current run ends at the most recent logged bucket, not at now, so a sync
     * running behind holds it. Future-dated buckets count towards neither.
     *
     * @param  list<int>  $buckets
     * @return array{current: int, longest: int}
     */
    private function runs(array $buckets, Cadence $cadence): array
    {
        // Local wall clock, not CarbonImmutable::now(): app.timezone is UTC,
        // which would put "now" in the wrong bucket during BST.
        $now = $cadence->index(EntryInstant::nowLocal());
        $longest = 0;
        $run = 0;
        $previous = null;

        foreach ($buckets as $bucket) {
            if ($bucket > $now) {
                break;
            }

            $run = ($previous !== null && $bucket === $previous + 1) ? $run + 1 : 1;
            $longest = max($longest, $run);
            $previous = $bucket;
        }

        return ['current' => $run, 'longest' => $longest];
    }
}
