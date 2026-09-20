<?php

namespace App\Presenters\Exports;

use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Enums\TimelineType;
use App\Models\Film;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Presenters\Exports\Sheets\FilmSheet;
use App\Support\SerialNumber;
use App\Support\Units;

/**
 * A film as an export: what was watched, its rating, and the named meta
 * values worth publishing. No aspects: a film has neither a place nor a span.
 */
final class FilmExport
{
    public function sheet(): FilmSheet
    {
        return new FilmSheet;
    }

    public function present(Film $model): ExportData
    {
        $card = CardPresenter::for($model);

        return new ExportData(
            type: TimelineType::Film,
            url: url($model->url()),
            title: $card->title,
            summary: EntryDescription::for($model, $card),
            occurred: $model->occurred_at === null ? null : ExportInstant::for($model->occurred_at, $model->timezone()),
            fields: array_values(array_filter([
                ExportField::maybe('film', 'Film', $model->title, $model->title),
                ExportField::maybe('date', 'Date', $model->occurred_at?->format('d M Y')),
                ExportField::maybe('rating', 'Rating', $model->rating === null ? null : "{$model->rating} out of 10", $model->rating),
                ExportField::maybe('year', 'Released', $model->meta->year === null ? null : (string) $model->meta->year, $model->meta->year),
                ExportField::maybe('runtime', 'Runtime', $model->meta->runtime === null ? null : Units::humanDuration($model->meta->runtime * 60), $model->meta->runtime === null ? null : $model->meta->runtime * 60),
                ExportField::maybe('owner', 'Name', config('identity.name')),
                ExportField::make('ticket_number', 'Ticket no.', SerialNumber::for($model->occurred_at), $model->id),
            ])),
            links: CommonLinks::for($model),
        );
    }
}
