<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\SegmentData;
use App\Enums\TimelineType;
use App\Models\Sleep;

/**
 * Builds the timeline card for a Sleep log: total time asleep plus a
 * per-stage breakdown (awake/REM/light/deep) for the timeline bar.
 */
final class SleepCard
{
    public function present(Sleep $model): CardData
    {
        $totalMinutes = intdiv($model->duration, 60);
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        $formatted = $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";

        return new CardData(
            type: TimelineType::Sleep,
            icon: 'bed',
            title: "{$formatted} sleep",
            titleLabel: "Sleep log, {$formatted}",
            subtitle: $model->bedtime->format('g:ia').' → '.$model->wake_time->format('g:ia'),
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'sleep',
            range: null,
            meta: CardMeta::sleep($this->stageSegments($model)),
        );
    }

    /**
     * Per-stage durations (seconds) for the timeline breakdown bar.
     *
     * @return list<SegmentData>
     */
    private function stageSegments(Sleep $model): array
    {
        return collect([
            ['label' => 'Awake', 'stage' => 'awake', 'seconds' => (int) $model->awake],
            ['label' => 'REM', 'stage' => 'rem', 'seconds' => (int) $model->rem],
            ['label' => 'Light', 'stage' => 'light', 'seconds' => (int) $model->core],
            ['label' => 'Deep', 'stage' => 'deep', 'seconds' => (int) $model->deep],
        ])
            ->filter(fn (array $segment): bool => $segment['seconds'] > 0)
            ->map(fn (array $segment): SegmentData => new SegmentData($segment['label'], $segment['stage'], $segment['seconds']))
            ->values()
            ->all();
    }
}
