<?php

namespace App\Presenters\Exports;

use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Data\ExportLink;
use App\Enums\TimelineType;
use App\Models\TvEpisode;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Presenters\Exports\Sheets\TvEpisodeSheet;
use App\Support\ShowTitle;

/**
 * A TV episode as an export: what was watched, the show it belongs to, and
 * where in the run. No aspects: an episode has neither a place nor a span.
 */
final class TvEpisodeExport
{
    public function sheet(): TvEpisodeSheet
    {
        return new TvEpisodeSheet;
    }

    public function present(TvEpisode $model): ExportData
    {
        $model->loadMissing('tvShow');

        $card = CardPresenter::for($model);
        $show = ShowTitle::for($model);

        return new ExportData(
            type: TimelineType::TvEpisode,
            url: url($model->url()),
            title: $card->title,
            summary: EntryDescription::for($model, $card),
            occurred: $model->occurred_at === null ? null : ExportInstant::for($model->occurred_at, $model->timezone()),
            fields: array_values(array_filter([
                ExportField::maybe('episode', 'Episode', $model->title, $model->title),
                ExportField::maybe('show', 'Show', $show, $show),
                ExportField::maybe('season', 'Season', $model->meta->season === null ? null : (string) $model->meta->season, $model->meta->season),
                ExportField::maybe('number', 'Number', $model->meta->episode === null ? null : (string) $model->meta->episode, $model->meta->episode),
                ExportField::maybe('rating', 'Rating', $model->rating === null ? null : "{$model->rating} out of 10", $model->rating),
            ])),
            links: [
                ...array_values(array_filter([
                    ExportLink::maybe('show', 'Show', $model->tvShow?->title, $model->tvShow?->url()),
                ])),
                ...CommonLinks::for($model),
            ],
        );
    }
}
