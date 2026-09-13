<?php

namespace App\Queries;

use App\Enums\ActivityDiscipline;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Film;
use App\Models\Flight;
use App\Models\Food;
use App\Models\Note;
use App\Models\Place;
use App\Models\Sleep;
use App\Models\TvEpisode;
use Illuminate\Support\Carbon;

/**
 * Roll-up stat row for the year and month pages. Every stat self-hides at
 * zero, so sparse periods just show fewer numbers. $withSuperlative appends a
 * standout (e.g. the longest run), which reads as a headline over a year but
 * not over a single month.
 */
final class PeriodStats
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(Carbon $start, Carbon $end, bool $withSuperlative = false): array
    {
        $between = fn ($query) => $query->listed()->whereBetween('occurred_at', [$start, $end]);

        $stats = [];

        $activities = $between(Activity::query())->count();

        if ($activities > 0) {
            $stats[] = ['label' => 'Activities', 'value' => number_format($activities)];
        }

        /**
         * Distance split by discipline: a single lumped total hid that walking,
         * running and cycling are wildly different distances and efforts. Each
         * self-hides, so a period with only walks shows only "Walked".
         *
         * @var array<string, list<string>> $disciplines
         */
        $disciplines = [
            'Walked' => [ActivityDiscipline::Walk->value],
            'Ran' => [ActivityDiscipline::Run->value],
            'Cycled' => [ActivityDiscipline::Ride->value, ActivityDiscipline::EbikeRide->value],
        ];

        foreach ($disciplines as $label => $types) {
            $distanceM = (int) $between(Activity::query())->whereIn('type', $types)->sum('distance');

            if ($distanceM > 0) {
                $stats[] = ['label' => $label, 'distanceM' => $distanceM, 'precision' => 0];
            }
        }

        $avgSleep = (int) round($between(Sleep::query())->avg('duration') ?? 0);

        if ($avgSleep > 0) {
            $stats[] = ['label' => 'Avg sleep', 'seconds' => $avgSleep];
        }

        // Averaged over logged days only, so a partial period isn't diluted by
        // untracked ones.
        $foodDays = (int) $between(Food::query())->toBase()->selectRaw('COUNT(DISTINCT DATE(occurred_at)) as days')->value('days');

        if ($foodDays > 0) {
            $avgCalories = (int) round($between(Food::query())->sum('calories') / $foodDays);

            if ($avgCalories > 0) {
                $stats[] = ['label' => 'Food', 'value' => number_format($avgCalories), 'unit' => 'kcal/day'];
            }
        }

        $films = $between(Film::query())->count() + $between(TvEpisode::query())->count();

        if ($films > 0) {
            $stats[] = ['label' => 'Watched', 'value' => number_format($films)];
        }

        $flights = $between(Flight::query())->count();

        if ($flights > 0) {
            $stats[] = ['label' => 'Flights', 'value' => number_format($flights)];
        }

        $places = $between(Place::query())->count();

        if ($places > 0) {
            $stats[] = ['label' => 'Places', 'value' => number_format($places)];
        }

        $written = $between(Article::query())->count() + $between(Note::query())->count();

        if ($written > 0) {
            $stats[] = ['label' => 'Written', 'value' => number_format($written)];
        }

        // Year-scale superlative: the standout single run of the period.
        if ($withSuperlative) {
            $longestRun = (int) $between(Activity::query())->where('type', ActivityDiscipline::Run->value)->max('distance');

            if ($longestRun > 0) {
                $stats[] = ['label' => 'Longest run', 'distanceM' => $longestRun, 'precision' => 1];
            }
        }

        return $stats;
    }
}
