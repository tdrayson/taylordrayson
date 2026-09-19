<?php

namespace App\Presenters\Exports;

use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Data\ExportLink;
use App\Enums\TimelineType;
use App\Models\Project;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;

/**
 * A project as an export: its stage plus links to the live site and the
 * repository. No aspects: a project has neither a place nor a span.
 */
final class ProjectExport
{
    public function present(Project $model): ExportData
    {
        $card = CardPresenter::for($model);

        return new ExportData(
            type: TimelineType::Project,
            url: url($model->url()),
            title: $card->title,
            summary: EntryDescription::for($model, $card),
            occurred: $model->occurred_at === null ? null : ExportInstant::for($model->occurred_at, $model->timezone()),
            fields: array_values(array_filter([
                ExportField::maybe('project', 'Project', $model->title, $model->title),
                ExportField::maybe('stage', 'Stage', $model->stage?->label(), $model->stage?->value),
            ])),
            links: [
                ...array_values(array_filter([
                    // getAttributeValue(), not ->url: the model's inherited url() page-address
                    // method collides with this column's name. See #473.
                    ExportLink::maybe('site', 'Site', 'Visit the site', $model->getAttributeValue('url')),
                    ExportLink::maybe('code', 'Code', 'View the repository', $model->github_url),
                ])),
                ...CommonLinks::for($model),
            ],
            body: $model->long_description,
        );
    }
}
