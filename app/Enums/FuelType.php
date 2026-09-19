<?php

namespace App\Enums;

/**
 * Reference enum for a vehicle's `fuel_type` config value, NOT a model cast:
 * the value lives in `config/vehicles.php` rather than a database column, and
 * vehicles may move to real records later, so an unrecognised value matches
 * no case rather than throwing.
 */
enum FuelType: string
{
    case Petrol = 'petrol';
    case Diesel = 'diesel';

    public function label(): string
    {
        return match ($this) {
            self::Petrol => 'Petrol',
            self::Diesel => 'Diesel',
        };
    }
}
