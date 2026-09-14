<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Project;
use App\Support\Text;

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
            title: $this->title($model),
            titleLabel: null,
            subtitle: $model->description,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            range: null,
            meta: CardMeta::empty(),
        );
    }

    /** My description of the project, else a line naming it. */
    public function description(Project $model): string
    {
        return Text::prose($model->description) ?? "{$model->title}, a project of mine.";
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
