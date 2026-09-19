<?php

namespace App\Presenters\Exports;

use App\Data\Aspects\Geometry;
use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Enums\TimelineType;
use App\Models\Fuel;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Support\Money;

/**
 * A fuel fill-up as an export: the station and cost, with a point geometry
 * when the forecourt was geocoded so .geojson becomes available.
 */
final class FuelExport
{
    public function present(Fuel $model): ExportData
    {
        $card = CardPresenter::for($model);

        return new ExportData(
            type: TimelineType::Fuel,
            url: url($model->url()),
            title: $card->title,
            summary: EntryDescription::for($model, $card),
            occurred: $model->occurred_at === null ? null : ExportInstant::for($model->occurred_at, $model->timezone()),
            fields: array_values(array_filter([
                ExportField::maybe('station', 'Station', $this->station($model), $model->station_name),
                $this->location($model),
                ExportField::maybe('litres', 'Fuel', $model->litres === null ? null : number_format((float) $model->litres, 3).' L', $model->litres === null ? null : (float) $model->litres),
                ExportField::maybe('price_per_litre', 'Price', $model->price_per_litre === null ? null : Money::pencePerLitre($model->price_per_litre).' per litre', $model->price_per_litre === null ? null : (float) $model->price_per_litre),
                ExportField::maybe('cost', 'Cost', Money::gbp($model->cost), $model->cost === null ? null : (float) $model->cost),
                ExportField::maybe('odometer', 'Odometer', $model->odometer === null ? null : number_format($model->odometer).' miles', $model->odometer),
            ])),
            links: CommonLinks::for($model),
            aspects: array_filter([
                Geometry::class => $model->latitude === null ? null : Geometry::point((float) $model->latitude, (float) $model->longitude),
            ]),
        );
    }

    /** The station name, with its brand alongside when known: "Beddington Lane (BP)". */
    private function station(Fuel $model): ?string
    {
        if ($model->station_name === null) {
            return null;
        }

        return $model->brand === null ? $model->station_name : "{$model->station_name} ({$model->brand})";
    }

    private function location(Fuel $model): ?ExportField
    {
        $display = collect([$model->address, $model->city, $model->postcode])->filter()->implode(', ');

        if ($display === '') {
            return null;
        }

        return ExportField::make('location', 'Where', $display, [
            'lat' => $model->latitude === null ? null : (float) $model->latitude,
            'lng' => $model->longitude === null ? null : (float) $model->longitude,
            'address' => $display,
        ]);
    }
}
