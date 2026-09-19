<?php

namespace App\Models;

use App\Models\Concerns\HasLookupCsv;
use Database\Factories\AirportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    /** @use HasFactory<AirportFactory> */
    use HasFactory;

    use HasLookupCsv;

    public $timestamps = false;

    /**
     * Declared explicitly because getRows() is empty under test, leaving
     * Sushi nothing to infer the column types from.
     *
     * @var array<string, string>
     */
    protected $schema = [
        'iata_code' => 'string',
        'icao_code' => 'string',
        'name' => 'string',
        'city' => 'string',
        'country' => 'string',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['place'];

    protected function lookupPath(): string
    {
        return database_path('lookups/airports.csv');
    }

    protected function lookupKey(): string
    {
        return 'iata_code';
    }

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
