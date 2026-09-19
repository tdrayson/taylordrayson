<?php

namespace App\Presenters\Exports;

use App\Data\Aspects\Geometry;
use App\Data\Aspects\Span;
use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Enums\TimelineType;
use App\Models\Activity;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Support\Distance;
use App\Support\Units;
use Illuminate\Support\Str;

/**
 * An activity as an export: its own facts, a GPS track when Strava sent one
 * (only around half do, so .geojson is conditional), and a moment span so
 * .ics becomes available.
 */
final class ActivityExport
{
    public function present(Activity $model): ExportData
    {
        $card = CardPresenter::for($model);

        return new ExportData(
            type: TimelineType::Activity,
            url: url($model->url()),
            title: $card->title,
            summary: EntryDescription::for($model, $card),
            occurred: $model->occurred_at === null ? null : ExportInstant::for($model->occurred_at, $model->timezone()),
            fields: array_values(array_filter([
                ExportField::maybe('activity', 'Activity', $model->type === null ? null : Str::headline($model->type), $model->type),
                ExportField::maybe('name', 'Name', $model->name, $model->name),
                ExportField::maybe('distance', 'Distance', $model->distance === null ? null : number_format(Distance::miles($model->distance, 1), 1).' miles', $model->distance),
                ExportField::maybe('duration', 'Duration', $model->duration === null ? null : Units::humanDuration($model->duration), $model->duration),
                ExportField::maybe('calories', 'Calories', $model->calories === null ? null : number_format($model->calories).' kcal', $model->calories),
                ExportField::maybe('average_heart_rate', 'Average heart rate', $model->average_heart_rate === null ? null : round($model->average_heart_rate).' bpm', $model->average_heart_rate),
                ExportField::maybe('max_heart_rate', 'Max heart rate', $model->max_heart_rate === null ? null : $model->max_heart_rate.' bpm', $model->max_heart_rate),
            ])),
            links: CommonLinks::for($model),
            aspects: array_filter([
                Geometry::class => $this->track($model),
                Span::class => Span::moment($model->occurred_at, $model->duration, $model->timezone()),
            ]),
        );
    }

    /**
     * The GPS track as a line, from `track` rows shaped {time, lat, lng}. Null
     * for an activity Strava sent with no track at all.
     */
    private function track(Activity $model): ?Geometry
    {
        $points = $model->track;

        if (! is_array($points) || $points === []) {
            return null;
        }

        return Geometry::lineString(array_map(
            fn (array $point): array => [(float) $point['lat'], (float) $point['lng']],
            $points,
        ));
    }
}
