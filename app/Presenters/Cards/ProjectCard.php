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
            type: TimelineType::Project,
            icon: 'rocket',
            title: $model->title,
            titleLabel: null,
            subtitle: $model->description,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'project',
            range: null,
            meta: CardMeta::empty(),
        );
    }
}
