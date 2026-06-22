<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Locale;

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
     * Human label combining the city (or airport name) with the full country
     * name, e.g. "London, United Kingdom".
     */
    protected function place(): Attribute
    {
        return Attribute::get(function (): string {
            $city = $this->city ?: $this->name;
            $country = $this->country
                ? (Locale::getDisplayRegion('en-'.$this->country, 'en') ?: $this->country)
                : null;

            return collect([$city, $country])->filter()->implode(', ');
        });
    }
}
