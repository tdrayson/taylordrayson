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
 * the window and sleep score as sentences, plus a per-stage breakdown
 * (awake/REM/light/deep) for the timeline bar.
 */
final class SleepCard
{
    public function present(Sleep $model): CardData
    {
        $formatted = Units::humanDuration($model->duration);

        return new CardData(
            type: $this->type(),
            icon: 'bed',
            title: $this->title($model),
            titleLabel: 'Sleep log, I slept for '.Units::spokenDuration($model->duration),
            subtitle: $this->sentence($model),
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'sleep',
            range: null,
            meta: CardMeta::sleep($this->stageSegments($model)),
        );
    }

    /**
     * The night as sentences. Bed and waking rather than "I slept", which the
     * title already says, and the score in a sentence of its own: "sleep score"
     * in full, the term SleepDetail.vue uses for the panel on the entry page.
     */
    private function sentence(Sleep $model): string
    {
        $window = sprintf(
            'I went to bed at %s and woke at %s.',
            $model->bedtime->format('g:ia'),
            $model->wake_time->format('g:ia'),
        );

        return $model->score
            ? "{$window} My sleep score was {$model->score}."
            : $window;
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

    public function title(Sleep $model): string
    {
        return 'I slept for '.Units::humanDuration($model->duration);
    }

    public function type(): TimelineType
    {
        return TimelineType::Sleep;
    }
}
