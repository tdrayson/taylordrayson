<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\FieldType;

/**
 * A fill-up. The garage is stored flat on the row rather than as a relation,
 * so its name and location are ordinary fields here.
 *
 * Money is 2dp everywhere except price per litre, which is the 3dp exception.
 */
final class FuelFields
{
    /**
     * @return list<FieldData>
     */
    public static function fields(): array
    {
        return [
            FieldData::primary('occurred_at', 'Date', FieldType::DateTime, defaultsToNow: true),
            FieldData::primary('litres', 'Litres', FieldType::Number, required: true),
            FieldData::primary('cost', 'Cost', FieldType::Number, 'Total paid, to 2dp.', required: true),
            FieldData::primary('station_name', 'Garage', FieldType::Location, 'Search stations, or use your location.', source: 'station'),
            FieldData::primary('vehicle_id', 'Vehicle', FieldType::Select, null, self::vehicleOptions()),
            FieldData::optional('brand', 'Brand', FieldType::Lookup, 'Drives the logo shown on the card.', source: 'fuel-brand'),
            FieldData::optional('price_per_litre', 'Price per litre', FieldType::Number, 'The 3dp exception to 2dp money.'),
            FieldData::optional('fuel_card_cost', 'Fuel card cost', FieldType::Number),
            FieldData::optional('odometer', 'Odometer', FieldType::Number),
            FieldData::optional('address', 'Address', FieldType::Text),
            FieldData::optional('postcode', 'Postcode', FieldType::Text),
            FieldData::optional('city', 'City', FieldType::Text),
            FieldData::optional('county', 'County', FieldType::Text),
            FieldData::optional('country', 'Country', FieldType::Text),
            FieldData::optional('latitude', 'Latitude', FieldType::Number),
            FieldData::optional('longitude', 'Longitude', FieldType::Number),
        ];
    }

    /**
     * Cars come from config rather than a table (see config/vehicles.php), so
     * the choices are built from it and a second car needs no code change here.
     *
     * @return list<array{value: string, label: string}>
     */
    private static function vehicleOptions(): array
    {
        $vehicles = config('vehicles', []);

        return array_values(array_map(
            fn (string $id, array $vehicle): array => [
                'value' => $id,
                'label' => trim(sprintf('%s %s (%s)', $vehicle['make'] ?? '', $vehicle['model'] ?? '', strtoupper($id))),
            ],
            array_keys($vehicles),
            $vehicles,
        ));
    }
}
