<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\TimelineType;
use App\Models\Fuel;
use App\Presenters\Cards\FuelCard;
use App\Timeline\Taxonomies;

/**
 * Fuel fill-ups, logged per vehicle.
 */
final class FuelDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::Fuel;
    }

    public function model(): string
    {
        return Fuel::class;
    }

    public function kind(): DatasetKind
    {
        return DatasetKind::Travel;
    }

    public function icon(): string
    {
        return 'PetrolPumpIcon';
    }

    public function label(): string
    {
        return 'Fuel';
    }

    public function plural(): string
    {
        return 'Fuel';
    }

    public function slug(): string
    {
        return 'fuel';
    }

    public function keywords(): string
    {
        return 'petrol gas diesel';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function countNouns(): array
    {
        return ['fill-up', 'fill-ups'];
    }

    public function card(): FuelCard
    {
        return new FuelCard;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        return [
            // Filter is still typed as `station:`; the column behind it became
            // station_name when the station moved onto the row.
            'station' => ['label' => 'Station', 'dataType' => 'text', 'column' => 'station_name', 'category' => 'Fuel'],
            'city' => ['label' => 'City', 'dataType' => 'text', 'column' => 'city', 'category' => 'Fuel'],
            'litres' => ['label' => 'Litres', 'dataType' => 'number', 'column' => 'litres', 'category' => 'Cost', 'suffix' => 'L'],
            'cost' => ['label' => 'Cost', 'dataType' => 'number', 'column' => 'cost', 'category' => 'Cost', 'prefix' => '£'],
            'price' => ['label' => 'Price / litre', 'dataType' => 'number', 'column' => 'price_per_litre', 'category' => 'Cost', 'prefix' => '£'],
            'odometer' => ['label' => 'Odometer', 'dataType' => 'number', 'column' => 'odometer', 'category' => 'Cost', 'measure' => 'distance', 'store' => 'mi'],
        ];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return ['station_name', 'city'];
    }

    public function taxonomy(): callable
    {
        return Taxonomies::vehicle();
    }

    public function draftable(): bool
    {
        return true;
    }
}
