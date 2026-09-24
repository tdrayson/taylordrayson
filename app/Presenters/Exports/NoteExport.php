<?php

namespace App\Presenters\Exports;

use App\Data\ExportData;
use App\Data\ExportInstant;
use App\Enums\TimelineType;
use App\Models\Note;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Presenters\Exports\Sheets\NoteSheet;

/**
 * A note as an export: no fields of its own, only the Portable Text document
 * as the body. No title either, so mf2 withholds p-name for it.
 */
final class NoteExport
{
    public function sheet(): NoteSheet
    {
        return new NoteSheet;
    }

    public function present(Note $model): ExportData
    {
        $card = CardPresenter::for($model);

        return new ExportData(
            type: TimelineType::Note,
            url: url($model->url()),
            title: $card->title,
            summary: EntryDescription::for($model, $card),
            occurred: $model->occurred_at === null ? null : ExportInstant::for($model->occurred_at, $model->timezone()),
            fields: [],
            links: CommonLinks::for($model),
            body: $model->content,
        );
    }
}
