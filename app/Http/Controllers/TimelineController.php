<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Calorie;
use App\Models\Podcast;
use App\Models\Sleep;
use App\Models\TimelineEntry;
use Illuminate\View\View;

class TimelineController extends Controller
{
    public function index(): View
    {
        $entries = TimelineEntry::query()
            ->with('timelineable')
            ->orderByDesc('occurred_at')
            ->paginate(20);

        $streak = $this->calculateCalorieStreak();

        $sparklines = $this->calculateSparklines();

        $episodeCount = Podcast::count();

        return view('pages.home', [
            'entries' => $entries,
            'streak' => $streak,
            'sparklines' => $sparklines,
            'episodeCount' => $episodeCount,
        ]);
    }

    private function calculateCalorieStreak(): int
    {
        $streak = 0;
        $date = today();

        while (true) {
            $hasCalories = Calorie::whereDate('occurred_at', $date)->exists();
            if (! $hasCalories) {
                break;
            }
            $streak++;
            $date = $date->subDay();
        }

        return $streak;
    }

    /**
     * @return array{running: array{values: list<float>, avg: float}, calories: array{values: list<int>, avg: int}, sleep: array{values: list<float>, avg: float}}
     */
    private function calculateSparklines(): array
    {
        $days = 14;
        $running = [];
        $calories = [];
        $sleep = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = today()->subDays($i);

            $running[] = (float) Activity::whereDate('occurred_at', $date)
                ->where('type', 'run')
                ->sum('distance_km');

            $calories[] = (int) Calorie::whereDate('occurred_at', $date)
                ->sum('calories');

            $sleepEntry = Sleep::whereDate('occurred_at', $date)->first();
            $sleep[] = $sleepEntry ? round($sleepEntry->duration_minutes / 60, 1) : 0;
        }

        return [
            'running' => [
                'values' => $running,
                'avg' => count(array_filter($running)) > 0
                    ? round(array_sum($running) / max(count(array_filter($running)), 1), 1)
                    : 0,
            ],
            'calories' => [
                'values' => $calories,
                'avg' => count(array_filter($calories)) > 0
                    ? (int) round(array_sum($calories) / max(count(array_filter($calories)), 1))
                    : 0,
            ],
            'sleep' => [
                'values' => $sleep,
                'avg' => count(array_filter($sleep)) > 0
                    ? round(array_sum($sleep) / max(count(array_filter($sleep)), 1), 1)
                    : 0,
            ],
        ];
    }
}
