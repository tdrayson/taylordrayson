<?php

namespace App\Presenters\Exports;

use App\Data\Aspects\Span;
use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Data\ExportLink;
use App\Enums\TimelineType;
use App\Models\ThisWeekWith;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Support\Units;

/**
 * A This Week With episode as an export: where it sits in the run and its
 * topic, with a moment span so .ics becomes available.
 */
final class ThisWeekWithExport
{
    public function present(ThisWeekWith $model): ExportData
    {
        $card = CardPresenter::for($model);

        return new ExportData(
            type: TimelineType::ThisWeekWith,
            url: url($model->url()),
            title: $card->title,
            summary: EntryDescription::for($model, $card),
            occurred: $model->occurred_at === null ? null : ExportInstant::for($model->occurred_at, $model->timezone()),
            fields: array_values(array_filter([
                ExportField::make('episode', 'Episode', "S{$model->season_number}E{$model->episode_number}", ['season' => $model->season_number, 'episode' => $model->episode_number]),
                ExportField::maybe('topic', 'Topic', $model->topic, $model->topic),
                ExportField::maybe('duration', 'Duration', $model->duration === null ? null : Units::humanDuration($model->duration), $model->duration),
            ])),
            links: [
                ...array_values(array_filter([
                    ExportLink::maybe('listen', 'Listen', 'Listen', $model->audio_url),
                    ExportLink::maybe('watch', 'Watch', 'Watch on YouTube', $model->video_url),
                ])),
                ...CommonLinks::for($model),
            ],
            body: $model->show_notes,
            aspects: array_filter([
                Span::class => Span::moment($model->occurred_at, $model->duration, $model->timezone()),
            ]),
        );
    }
}
