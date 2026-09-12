<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Project;

/**
 * Builds the timeline card for a Project: description as the subtitle, no
 * media meta.
 */
final class ProjectCard
{
    public function present(Project $model): CardData
    {
        return new CardData(
            type: $this->type(),
            icon: 'rocket',
            title: $this->title($model),
            titleLabel: null,
            subtitle: $model->description,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'project',
            range: null,
            meta: CardMeta::empty(),
        );
    }

    public function title(Project $model): string
    {
        return $model->title;
    }

    public function type(): TimelineType
    {
        return TimelineType::Project;
    }
}
