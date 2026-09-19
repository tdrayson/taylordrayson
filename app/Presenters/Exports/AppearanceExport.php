<?php

namespace App\Presenters\Exports;

use App\Data\Aspects\Span;
use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Data\ExportLink;
use App\Enums\TimelineType;
use App\Models\Appearance;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Support\Units;
use Illuminate\Support\Str;

/**
 * A speaking appearance as an export: what it was and where to listen or
 * watch, with a moment span so .ics becomes available.
 */
final class AppearanceExport
{
    public function present(Appearance $model): ExportData
    {
        $card = CardPresenter::for($model);

        return new ExportData(
            type: TimelineType::Appearance,
            url: url($model->url()),
            title: $card->title,
            summary: EntryDescription::for($model, $card),
            occurred: $model->occurred_at === null ? null : ExportInstant::for($model->occurred_at, $model->timezone()),
            fields: array_values(array_filter([
                ExportField::maybe('appearance', 'Appearance', $model->title, $model->title),
                ExportField::maybe('kind', 'Kind', $model->type === null ? null : Str::headline($model->type), $model->type),
                ExportField::maybe('show', 'Show', $model->show_name, $model->show_name),
                ExportField::maybe('duration', 'Duration', $model->duration === null ? null : Units::humanDuration($model->duration), $model->duration),
            ])),
            links: [
                ...array_values(array_filter([
                    ExportLink::maybe('listen', 'Listen', 'Listen', $model->audio_url),
                    ExportLink::maybe('watch', 'Watch', 'Watch on YouTube', $model->video_url),
                    // getAttributeValue(), not ->url: the model's inherited url() page-address
                    // method collides with this column's name. See #473.
                    ExportLink::maybe('source', 'Source', 'Show page', $model->getAttributeValue('url'), 'syndication'),
                ])),
                ...CommonLinks::for($model),
            ],
            body: $model->description,
            aspects: array_filter([
                Span::class => Span::moment($model->occurred_at, $model->duration, $model->timezone()),
            ]),
        );
    }
}
