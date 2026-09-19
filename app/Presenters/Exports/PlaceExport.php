<?php

namespace App\Presenters\Exports;

use App\Data\Aspects\Geometry;
use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Data\ExportLink;
use App\Enums\TimelineType;
use App\Models\Place;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Presenters\Exports\Sheets\PlaceSheet;
use App\Timeline\TypeRegistry;
use Illuminate\Support\Str;

/**
 * A place check-in as an export: the venue and its category, with a point
 * geometry so .geojson becomes available.
 */
final class PlaceExport
{
    public function sheet(): PlaceSheet
    {
        return new PlaceSheet;
    }

    public function present(Place $model): ExportData
    {
        $card = CardPresenter::for($model);

        return new ExportData(
            type: TimelineType::Place,
            url: url($model->url()),
            title: $card->title,
            summary: EntryDescription::for($model, $card),
            occurred: $model->occurred_at === null ? null : ExportInstant::for($model->occurred_at, $model->timezone()),
            fields: array_values(array_filter([
                ExportField::maybe('venue', 'Place', $model->venue_name, $model->venue_name),
                ExportField::maybe('category', 'Category', $model->type === null ? null : Str::headline($model->type), $model->type),
                $this->location($model),
                ExportField::maybe('event', 'For', $model->event_name, $model->event_name),
            ])),
            links: [
                ...array_values(array_filter([$this->categoryLink($model)])),
                ...CommonLinks::for($model),
            ],
            body: $model->description,
            aspects: array_filter([
                Geometry::class => $model->latitude === null ? null : Geometry::point((float) $model->latitude, (float) $model->longitude),
            ]),
        );
    }

    private function location(Place $model): ?ExportField
    {
        $display = collect([$model->address, $model->city, $model->postcode, $model->country])->filter()->implode(', ');

        if ($display === '') {
            return null;
        }

        return ExportField::make('location', 'Where', $display, [
            'lat' => $model->latitude === null ? null : (float) $model->latitude,
            'lng' => $model->longitude === null ? null : (float) $model->longitude,
            'address' => $display,
        ]);
    }

    /** The place's category archive, alongside the generic type link Common builds. */
    private function categoryLink(Place $model): ?ExportLink
    {
        if ($model->type === null) {
            return null;
        }

        $base = TypeRegistry::find('place')['taxonomy']['base'];

        return ExportLink::make('category', 'Category', Str::headline($model->type), "/{$base}/".Str::slug($model->type));
    }
}
