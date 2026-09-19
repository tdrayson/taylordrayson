<?php

namespace App\Presenters\Exports;

use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Enums\TimelineType;
use App\Models\Book;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Presenters\Exports\Sheets\BookSheet;

/**
 * A book as an export: what was read, its rating and progress. No aspects: a
 * book has neither a place nor a span.
 */
final class BookExport
{
    public function sheet(): BookSheet
    {
        return new BookSheet;
    }

    public function present(Book $model): ExportData
    {
        $card = CardPresenter::for($model);

        return new ExportData(
            type: TimelineType::Book,
            url: url($model->url()),
            title: $card->title,
            summary: EntryDescription::for($model, $card),
            occurred: $model->occurred_at === null ? null : ExportInstant::for($model->occurred_at, $model->timezone()),
            fields: array_values(array_filter([
                ExportField::maybe('book', 'Book', $model->title, $model->title),
                ExportField::maybe('author', 'Author', $model->meta->author, $model->meta->author),
                ExportField::maybe('rating', 'Rating', $model->rating === null ? null : "{$model->rating} out of 10", $model->rating),
                ExportField::maybe('pages', 'Pages', $model->pages === null ? null : number_format($model->pages), $model->pages),
                ExportField::maybe('progress', 'Progress', $model->progress_percent === null ? null : round($model->progress_percent).'%', $model->progress_percent),
                ExportField::maybe('started', 'Started', $model->started_at?->format('j F Y'), $model->started_at?->toIso8601String()),
            ])),
            links: CommonLinks::for($model),
            body: $model->overview,
        );
    }
}
