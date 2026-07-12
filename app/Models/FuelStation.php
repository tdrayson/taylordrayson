<?php

namespace App\Models;

use App\Contracts\DefinesContentSchema;
use Database\Factories\FuelStationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;

#[Fillable([
    'name',
    'brand',
    'address',
    'city',
    'country',
    'latitude',
    'longitude',
])]
class FuelStation extends Model implements DefinesContentSchema
{
    /** @use HasFactory<FuelStationFactory> */
    use HasFactory;

    public static function schema(Blueprint $table): void
    {
        $table->id();
        $table->string('name');
        $table->string('brand')->nullable();
        $table->string('address')->nullable();
        $table->string('city')->nullable();
        $table->string('country')->nullable();
        $table->decimal('latitude', 10, 7)->nullable();
        $table->decimal('longitude', 10, 7)->nullable();
        $table->timestamps();
    }

    public function fuelEntries(): HasMany
    {
        return $this->hasMany(Fuel::class);
    }
}
