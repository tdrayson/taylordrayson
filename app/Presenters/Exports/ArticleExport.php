<?php

namespace App\Presenters\Exports;

use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Enums\TimelineType;
use App\Models\Article;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Presenters\Exports\Sheets\ArticleSheet;

/**
 * An article as an export: its title and excerpt, with the Portable Text
 * document itself as the body. No aspects: an article has neither a place
 * nor a span.
 */
final class ArticleExport
{
    public function sheet(): ArticleSheet
    {
        return new ArticleSheet;
    }

    public function present(Article $model): ExportData
    {
        $card = CardPresenter::for($model);

        return new ExportData(
            type: TimelineType::Article,
            url: url($model->url()),
            title: $card->title,
            summary: EntryDescription::for($model, $card),
            occurred: $model->occurred_at === null ? null : ExportInstant::for($model->occurred_at, $model->timezone()),
            fields: array_values(array_filter([
                ExportField::maybe('article', 'Article', $model->title, $model->title),
                ExportField::maybe('excerpt', 'Excerpt', $model->excerpt, $model->excerpt),
            ])),
            links: CommonLinks::for($model),
            body: $model->content,
        );
    }
}
