<?php

namespace App\Models;

use Database\Factories\FuelStationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'city',
    'country',
    'latitude',
    'longitude',
])]
class FuelStation extends Model
{
    /** @use HasFactory<FuelStationFactory> */
    use HasFactory;

    public function fuelEntries(): HasMany
    {
        return $this->hasMany(Fuel::class);
    }
}
