<?php

namespace App\Presenters\Exports;

use App\Data\Aspects\Geometry;
use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Enums\FuelType;
use App\Enums\TimelineType;
use App\Models\Fuel;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Presenters\Exports\Sheets\FuelSheet;
use App\Support\Money;
use App\Support\SerialNumber;

/**
 * A fuel fill-up as an export: the station and cost, with a point geometry
 * when the forecourt was geocoded so .geojson becomes available.
 */
final class FuelExport
{
    /** UK pump petrol moved from grade E5 to E10 on this date; see {@see petrolGrade()}. */
    private const E10_CUTOVER = '2021-09-01';

    public function sheet(): FuelSheet
    {
        return new FuelSheet;
    }

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
                ExportField::maybe('station', 'Station', $model->station_name),
                ExportField::maybe('brand', 'Brand', $model->brand),
                ExportField::maybe('fuel_type', 'Fuel type', $this->fuelType($model)),
                ExportField::maybe('locality', 'Locality', $this->locality($model)),
                $this->location($model),
                ExportField::maybe('receipt_time', 'Time', $this->receiptTime($model), $model->occurred_at?->toIso8601String()),
                ExportField::make('receipt_ref', 'Receipt ref', SerialNumber::for($model->occurred_at), $model->id),
                ExportField::maybe('litres', 'Fuel', $model->litres === null ? null : number_format((float) $model->litres, 2).' L', $model->litres === null ? null : (float) $model->litres),
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

    /** The town and postcode alone, short enough never to wrap mid-postcode the way the full address can. */
    private function locality(Fuel $model): ?string
    {
        $display = collect([$model->city, $model->postcode])->filter()->implode(', ');

        return $display === '' ? null : $display;
    }

    /**
     * The vehicle's fuel type from config, petrol refined to its pump grade.
     * Null when the vehicle or its fuel type is unrecognised, so the row is
     * dropped rather than guessing.
     */
    private function fuelType(Fuel $model): ?string
    {
        $vehicle = $model->vehicle;
        $type = FuelType::tryFrom((string) (is_array($vehicle) ? ($vehicle['fuel_type'] ?? '') : ''));

        return match ($type) {
            FuelType::Petrol => "{$type->label()} ({$this->petrolGrade($model)})",
            FuelType::Diesel => $type->label(),
            null => null,
        };
    }

    /**
     * Standard-grade petrol only (E5 before the UK's E10 switch, E10 after):
     * the owner has confirmed he has never bought premium/Super Unleaded,
     * which stayed E5 after the cutover. A future per-fill grade field
     * replaces this derivation.
     */
    private function petrolGrade(Fuel $model): string
    {
        return $model->occurred_at !== null && $model->occurred_at->toDateString() >= self::E10_CUTOVER ? 'E10' : 'E5';
    }

    private function receiptTime(Fuel $model): ?string
    {
        if ($model->occurred_at === null) {
            return null;
        }

        return mb_strtoupper($model->occurred_at->format('d-M-Y')).' '.$model->occurred_at->format('H:i');
    }
}
