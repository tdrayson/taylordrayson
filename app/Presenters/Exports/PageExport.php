<?php

namespace App\Presenters\Exports;

use App\Data\ExportData;
use App\Data\ExportLink;
use App\Models\Page;
use App\Presenters\Exports\Sheets\PageSheet;

/**
 * A content page as an export: no fields of its own, only the Portable Text
 * document as the body. No aspects: a page has neither a place nor a span.
 */
final class PageExport
{
    public function sheet(): PageSheet
    {
        return new PageSheet;
    }

    public function present(Page $model): ExportData
    {
        return new ExportData(
            type: 'page',
            url: url($model->url()),
            title: $model->title,
            summary: $model->excerpt,
            occurred: null,
            fields: [],
            links: [
                ExportLink::make('pages', 'All pages', 'All pages', '/pages'),
                ...CommonLinks::for($model),
            ],
            body: $model->content,
        );
    }
}
