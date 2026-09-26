<?php

namespace App\Queries;

use App\Data\EntryDay;
use App\Enums\ActivityLevel;
use Illuminate\Support\Carbon;

/**
 * Timeline entries per day with their activity shade, shared by the Now widget and the link page.
 */
final class EntryDays
{
    public function __construct(private readonly HeatmapDays $heatmapDays) {}

    /**
     * @param  Carbon  $start  The first day, in the home timezone.
     * @param  int  $days  How many days to cover; any after today come back empty.
     * @return list<EntryDay>
     */
    public function __invoke(Carbon $start, int $days): array
    {
        $today = Carbon::today((string) config('app.home_timezone'));
        $counts = ($this->heatmapDays)($start->copy()->startOfDay(), $today->copy()->endOfDay());

        return array_map(function (int $offset) use ($start, $today, $counts): EntryDay {
            $day = $start->copy()->addDays($offset);

            if ($day->gt($today)) {
                return new EntryDay($day->toDateString(), null, null);
            }

            $count = $counts[$day->toDateString()] ?? 0;

            return new EntryDay($day->toDateString(), $count, ActivityLevel::fromCount($count));
        }, range(0, $days - 1));
    }
}
