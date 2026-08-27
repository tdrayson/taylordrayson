<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\SegmentData;
use App\Enums\TimelineType;
use App\Models\Sleep;
use App\Support\Units;

/**
 * Builds the timeline card for a Sleep log: total time asleep as the title,
 * the window and sleep score as a sentence, plus a per-stage breakdown
 * (awake/REM/light/deep) for the timeline bar.
 */
final class SleepCard
{
    public function present(Sleep $model): CardData
    {
        $formatted = Units::humanDuration($model->duration);

        return new CardData(
            type: TimelineType::Sleep,
            icon: 'bed',
            title: "{$formatted} asleep",
            titleLabel: "Sleep log, {$formatted} asleep",
            subtitle: $this->sentence($model),
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'sleep',
            range: null,
            meta: CardMeta::sleep($this->stageSegments($model)),
        );
    }

    /**
     * The night as a sentence. "sleep score" in full rather than a bare number,
     * which on its own says nothing about what was scored; it is also the term
     * SleepDetail.vue already uses for the panel on the entry page.
     */
    private function sentence(Sleep $model): string
    {
        $window = sprintf(
            'I slept from %s to %s',
            $model->bedtime->format('g:ia'),
            $model->wake_time->format('g:ia'),
        );

        return $model->score
            ? "{$window}, with a sleep score of {$model->score}."
            : "{$window}.";
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
