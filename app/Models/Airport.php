<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'iata_code',
    'icao_code',
    'name',
    'city',
    'country',
    'latitude',
    'longitude',
])]
class Airport extends Model
{
    /**
     * @var list<string>
     */
    protected $appends = ['place'];

    /**
     * Human label combining the city (or airport name) with its country as the
     * short ISO code, e.g. "London, GB".
     */
    protected function place(): Attribute
    {
        return Attribute::get(function (): string {
            $city = $this->city ?: $this->name;
            $country = $this->country ? strtoupper($this->country) : null;

            return collect([$city, $country])->filter()->implode(', ');
        });
    }
}
