<?php

namespace App\Queries;

use App\Models\TimelineEntry;
use App\Support\SqlDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One page of the timeline, anchored to a date rather than an offset.
 *
 * A page is a run of whole days. Days are never split: the cursor is a date, so
 * there is no position that could describe half of one. How many days fit is
 * decided per page by two limits, whichever binds first.
 */
final class TimelineWindow
{
    /**
     * Entries a page aims for. Soft: a single day carrying more than this still
     * renders whole, since the alternative is splitting it.
     */
    private const ENTRY_BUDGET = 50;

    /**
     * Calendar days a page may span. Binds in the sparse early years, where the
     * budget alone would sweep 2003 to 2011 into one page and label it as such.
     */
    private const MAX_SPAN = 92;

    /** Logged days a page may hold, so a long quiet stretch still terminates. */
    private const MAX_DAYS = 31;

    /**
     * The page of days at the cursor, and the cursors either side of it.
     *
     * @param  string|null  $before  Y-m-d: start at the newest day older than this.
     * @param  string|null  $after  Y-m-d: start at the oldest day newer than this, filling forward.
     * @return array{from: ?string, to: ?string, olderThan: ?string, newerThan: ?string}
     */
    public function __invoke(?string $before = null, ?string $after = null): array
    {
        $forward = $after !== null;
        $candidates = $this->candidates($before, $after, $forward);

        if ($candidates->isEmpty()) {
            return ['from' => null, 'to' => null, 'olderThan' => null, 'newerThan' => null];
        }

        $taken = $this->fill($candidates);

        // Filling forward walks oldest-first, so the run reads the other way round.
        $days = $forward ? $taken->reverse()->values() : $taken;

        $newest = $days->first()['day'];
        $oldest = $days->last()['day'];

        return [
            'from' => $oldest,
            'to' => $newest,
            // Null when nothing lies beyond, which is what hides the link.
            'olderThan' => $this->exists($oldest, older: true) ? $oldest : null,
            'newerThan' => $this->exists($newest, older: false) ? $newest : null,
        ];
    }

    /**
     * Enough days beyond the cursor to fill a page, with their entry counts.
     *
     * @return Collection<int, array{day: string, total: int}>
     */
    private function candidates(?string $before, ?string $after, bool $forward): Collection
    {
        $date = SqlDate::date('occurred_at');

        return TimelineEntry::query()
            ->toBase()
            ->selectRaw("{$date} as day, count(*) as total")
            ->when($before !== null, fn ($query) => $query->whereRaw("{$date} < ?", [$before]))
            ->when($after !== null, fn ($query) => $query->whereRaw("{$date} > ?", [$after]))
            ->groupBy('day')
            ->orderBy('day', $forward ? 'asc' : 'desc')
            ->limit(self::MAX_DAYS)
            ->get()
            ->map(fn (object $row): array => ['day' => (string) $row->day, 'total' => (int) $row->total]);
    }

    /**
     * Take whole days until one of the limits would break, always at least one.
     *
     * @param  Collection<int, array{day: string, total: int}>  $candidates
     * @return Collection<int, array{day: string, total: int}>
     */
    private function fill(Collection $candidates): Collection
    {
        $taken = collect();
        $entries = 0;

        foreach ($candidates as $day) {
            if ($taken->isNotEmpty()) {
                $overBudget = $entries + $day['total'] > self::ENTRY_BUDGET;
                $overSpan = $this->span($taken->first()['day'], $day['day']) > self::MAX_SPAN;

                if ($overBudget || $overSpan) {
                    break;
                }
            }

            $taken->push($day);
            $entries += $day['total'];
        }

        return $taken;
    }

    /** Whole calendar days between two Y-m-d strings, in either order. */
    private function span(string $a, string $b): int
    {
        return (int) Carbon::parse($a)->diffInDays(Carbon::parse($b), absolute: true);
    }

    /** Whether any day lies beyond this one, which is what decides the links. */
    private function exists(string $day, bool $older): bool
    {
        $date = SqlDate::date('occurred_at');

        return TimelineEntry::query()
            ->toBase()
            ->whereRaw("{$date} ".($older ? '<' : '>').' ?', [$day])
            ->exists();
    }
}
