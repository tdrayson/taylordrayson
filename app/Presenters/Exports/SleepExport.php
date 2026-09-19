<?php

namespace App\Presenters\Exports;

use App\Data\Aspects\Span;
use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Enums\TimelineType;
use App\Models\Sleep;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Support\Units;
use Illuminate\Support\Str;

/**
 * A night's sleep as an export: stage durations and score, with a span from
 * bedtime to waking so .ics becomes available. Sleep is stored waking-at, so
 * occurred_at is the span's end, not its start.
 */
final class SleepExport
{
    public function present(Sleep $model): ExportData
    {
        $card = CardPresenter::for($model);

        return new ExportData(
            type: TimelineType::Sleep,
            url: url($model->url()),
            title: $card->title,
            summary: EntryDescription::for($model, $card),
            occurred: $model->occurred_at === null ? null : ExportInstant::for($model->occurred_at, $model->timezone()),
            fields: array_values(array_filter([
                ExportField::maybe('duration', 'Asleep', $model->duration === null ? null : Units::humanDuration($model->duration), $model->duration),
                ExportField::maybe('deep', 'Deep', $model->deep === null ? null : Units::humanDuration($model->deep), $model->deep),
                ExportField::maybe('core', 'Core', $model->core === null ? null : Units::humanDuration($model->core), $model->core),
                ExportField::maybe('rem', 'REM', $model->rem === null ? null : Units::humanDuration($model->rem), $model->rem),
                ExportField::maybe('awake', 'Awake', $model->awake === null ? null : Units::humanDuration($model->awake), $model->awake),
                ExportField::maybe('score', 'Score', $model->score === null ? null : $model->score.' out of 100', $model->score),
                ExportField::maybe('source', 'Recorded by', $model->source === null ? null : Str::headline($model->source), $model->source),
            ])),
            links: CommonLinks::for($model),
            aspects: array_filter([
                Span::class => Span::between($model->started_at, $model->occurred_at, $model->timezone()),
            ]),
        );
    }
}
